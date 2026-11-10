<?php

namespace Omnidns\Policy;

use Omnidns\NameserversInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's name servers behind their guard: each call is the operation
 * named after its method, "nameservers" or "setNameservers".
 *
 * @extends Guarded<NameserversInterface>
 */
final class GuardedNameservers extends Guarded implements NameserversInterface
{
    public function __construct(NameserversInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function nameservers(string $domain): array
    {
        return $this->guard->call('nameservers', fn () => $this->provider->nameservers($domain));
    }

    public function setNameservers(string $domain, array $nameservers): void
    {
        $this->guard->call('setNameservers', fn () => $this->provider->setNameservers($domain, $nameservers));
    }
}
