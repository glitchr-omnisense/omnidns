<?php

namespace Omnidns\Policy;

use Omnidns\DomainsInterface;
use Omnidns\Model\DomainInfo;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's domains behind its guard: each call is the operation named
 * after its method, "domains" or "domain".
 *
 * @extends Guarded<DomainsInterface>
 */
final class GuardedDomains extends Guarded implements DomainsInterface
{
    public function __construct(DomainsInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function domains(): array
    {
        return $this->guard->call('domains', fn () => $this->provider->domains());
    }

    public function domain(string $domain): DomainInfo
    {
        return $this->guard->call('domain', fn () => $this->provider->domain($domain));
    }
}
