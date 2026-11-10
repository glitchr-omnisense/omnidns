<?php

namespace Omnidns\Policy;

use Omnidns\Model\Contact;
use Omnidns\Model\Order;
use Omnidns\RegistrarInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's registrar behind its guard: each call is the operation named
 * after its method, "register" or "renew".
 *
 * @extends Guarded<RegistrarInterface>
 */
final class GuardedRegistrar extends Guarded implements RegistrarInterface
{
    public function __construct(RegistrarInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function register(string $domain, int $years = 1, ?Contact $owner = null): Order
    {
        return $this->guard->call('register', fn () => $this->provider->register($domain, $years, $owner));
    }

    public function renew(string $domain, int $years = 1): Order
    {
        return $this->guard->call('renew', fn () => $this->provider->renew($domain, $years));
    }
}
