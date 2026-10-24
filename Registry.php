<?php

namespace Omnidns;

use Omnidns\Exception\InvalidConfigException;
use Omnidns\Exception\NotSupportedException;

/**
 * The application's domain and DNS providers by name, each built once from
 * its factory and options:
 *
 *   $registry = new Registry([new OvhFactory(), new CloudflareFactory()], [
 *       'registrar' => ['factory' => 'ovh', 'options' => ['application_key' => '...', 'application_secret' => '...', 'consumer_key' => '...']],
 *       'dns' => ['factory' => 'cloudflare', 'options' => ['token' => '...']],
 *   ]);
 *
 *   $registry->availability('registrar')->check('example.org')->available;
 *   $registry->dns('dns')->records('example.org', 'MX');
 */
final class Registry
{
    /** @var array<string, ProviderFactoryInterface> */
    private array $factories = [];

    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<ProviderFactoryInterface>                                     $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
    }

    public function get(string $name): ProviderInterface
    {
        return $this->providers[$name] ??= $this->factory($name)->create($this->config[$name]['options'] ?? []);
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /** The provider, as the availability of a domain. */
    public function availability(string $name): AvailabilityInterface
    {
        $provider = $this->get($name);

        return $provider instanceof AvailabilityInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'check availability');
    }

    /** The provider, as a registrar. */
    public function registrar(string $name): RegistrarInterface
    {
        $provider = $this->get($name);

        return $provider instanceof RegistrarInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'register or renew domains');
    }

    /** The provider, as the account's domains. */
    public function domains(string $name): DomainsInterface
    {
        $provider = $this->get($name);

        return $provider instanceof DomainsInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'list domains');
    }

    /** The provider, as a domain's name servers. */
    public function nameservers(string $name): NameserversInterface
    {
        $provider = $this->get($name);

        return $provider instanceof NameserversInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'manage name servers');
    }

    /** The provider, as a zone's records. */
    public function dns(string $name): DnsInterface
    {
        $provider = $this->get($name);

        return $provider instanceof DnsInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'serve DNS records');
    }

    /** @return array<string, mixed> the options a provider is configured with */
    public function options(string $name): array
    {
        return $this->config[$name]['options'] ?? [];
    }

    /** @return list<string> the configured providers' names */
    public function names(): array
    {
        return array_keys($this->config);
    }

    /** @return list<string> the factories installed: what `factory:` may name */
    public function factories(): array
    {
        return array_keys($this->factories);
    }

    private function factory(string $name): ProviderFactoryInterface
    {
        $provider = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" DNS provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$provider['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" DNS provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }
}
