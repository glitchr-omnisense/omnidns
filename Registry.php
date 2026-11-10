<?php

namespace Omnidns;

use Omnidns\Exception\InvalidConfigException;
use Omnidns\Exception\NotSupportedException;
use Omnidns\Policy\GuardedAvailability;
use Omnidns\Policy\GuardedDns;
use Omnidns\Policy\GuardedDomains;
use Omnidns\Policy\GuardedNameservers;
use Omnidns\Policy\GuardedRegistrar;
use Omnisense\Contracts\Policy\RateLimiterInterface;
use Omnisense\Contracts\Provider\ProviderFactoryInterface as BaseFactoryInterface;
use Omnisense\Contracts\Provider\ProviderInterface as BaseProvider;
use Omnisense\Contracts\Provider\Registry as BaseRegistry;

/**
 * The application's domain and DNS providers by name, each built once from
 * its factory and options, each call through a contract guarded by the
 * provider's usage policy:
 *
 *   $registry = new Registry([new OvhFactory(), new CloudflareFactory()], [
 *       'registrar' => ['factory' => 'ovh', 'options' => ['application_key' => '...', 'application_secret' => '...', 'consumer_key' => '...']],
 *       'dns' => [
 *           'factory' => 'cloudflare',
 *           'options' => ['token' => '...'],
 *           'policy' => ['rateLimits' => [['max' => 100, 'window' => 'minute', 'operations' => ['add', 'update', 'delete']]]],
 *       ],
 *   ], $limiter);
 *
 *   $registry->availability('registrar')->check('example.org')->available;
 *   $registry->dns('dns')->records('example.org', 'MX');
 *
 * A typed getter gives the provider when it implements the interface and
 * its capabilities() say it gives that as configured (Cloudflare lists
 * domains only with an account_id): otherwise a NotSupportedException. It
 * gives it behind its guard (Policy\Guarded*): each call is checked against
 * the policy's rate limits for its operation - the contract's method name,
 * "check", "register", "renew", "domains", "domain", "nameservers",
 * "setNameservers", "records", "add", "update", "delete" - then counted,
 * and refused with Exception\UsageLimitReachedException once a limit is
 * reached or while the provider asks to wait (a 429 and its Retry-After).
 * get() gives the provider itself, unguarded. "policy" tightens the
 * provider's policy, never loosens it; "usage" says what the site is
 * (omnisense/contracts' Registry).
 */
final class Registry extends BaseRegistry
{
    /** @var string */
    public const PREFIX = Config::PREFIX;

    /** @var array<string, ProviderInterface> the guarded contracts, by "contract name" */
    private array $guarded = [];

    /**
     * @param iterable<ProviderFactoryInterface>                                                                                                $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>, policy?: array<string, mixed>, usage?: array<string, mixed>}> $config
     * @param ?RateLimiterInterface                                                                                                             $limiter   where every provider's calls are counted: an in-memory one by default, a shared one for an application served by many processes
     */
    public function __construct(iterable $factories, array $config, ?RateLimiterInterface $limiter = null)
    {
        parent::__construct($factories, $config, $limiter);
    }

    public function get(string $name): ProviderInterface
    {
        $provider = parent::get($name);

        return $provider instanceof ProviderInterface ? $provider : throw new InvalidConfigException(\sprintf('The "%s" DNS provider (%s) is no omnidns provider.', $name, $provider::class));
    }

    /** The provider, as the availability of a domain. */
    public function availability(string $name): AvailabilityInterface
    {
        $provider = $this->get($name);
        if (!$provider instanceof AvailabilityInterface || !$provider->capabilities()->availability) {
            throw NotSupportedException::capability($provider, $name, 'check availability');
        }
        $guarded = $this->guarded['availability '.$name] ??= new GuardedAvailability($provider, $this->guard($name));

        return $guarded instanceof AvailabilityInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /** The provider, as a registrar. */
    public function registrar(string $name): RegistrarInterface
    {
        $provider = $this->get($name);
        if (!$provider instanceof RegistrarInterface || !$provider->capabilities()->registrar) {
            throw NotSupportedException::capability($provider, $name, 'register or renew domains');
        }
        $guarded = $this->guarded['registrar '.$name] ??= new GuardedRegistrar($provider, $this->guard($name));

        return $guarded instanceof RegistrarInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /** The provider, as the account's domains. */
    public function domains(string $name): DomainsInterface
    {
        $provider = $this->get($name);
        if (!$provider instanceof DomainsInterface || !$provider->capabilities()->domains) {
            throw NotSupportedException::capability($provider, $name, 'list domains');
        }
        $guarded = $this->guarded['domains '.$name] ??= new GuardedDomains($provider, $this->guard($name));

        return $guarded instanceof DomainsInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /** The provider, as a domain's name servers. */
    public function nameservers(string $name): NameserversInterface
    {
        $provider = $this->get($name);
        if (!$provider instanceof NameserversInterface || !$provider->capabilities()->nameservers) {
            throw NotSupportedException::capability($provider, $name, 'manage name servers');
        }
        $guarded = $this->guarded['nameservers '.$name] ??= new GuardedNameservers($provider, $this->guard($name));

        return $guarded instanceof NameserversInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /** The provider, as a zone's records. */
    public function dns(string $name): DnsInterface
    {
        $provider = $this->get($name);
        if (!$provider instanceof DnsInterface || !$provider->capabilities()->dns) {
            throw NotSupportedException::capability($provider, $name, 'serve DNS records');
        }
        $guarded = $this->guarded['dns '.$name] ??= new GuardedDns($provider, $this->guard($name));

        return $guarded instanceof DnsInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /**
     * The configured providers' names, in the configuration's order; those
     * keeping a contract ("availability", "registrar", "domains",
     * "nameservers", "dns") when one is named.
     *
     * @return list<string>
     */
    public function names(?string $contract = null): array
    {
        return parent::names($contract);
    }

    protected function factory(string $name): BaseFactoryInterface
    {
        $provider = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" DNS provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$provider['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" DNS provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }

    protected function notSupported(BaseProvider $provider, string $name, string $capability): \Throwable
    {
        return NotSupportedException::capability($provider, $name, $capability);
    }
}
