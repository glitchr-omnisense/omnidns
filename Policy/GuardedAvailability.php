<?php

namespace Omnidns\Policy;

use Omnidns\AvailabilityInterface;
use Omnidns\Model\Availability;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's availability behind its guard: each call is the operation
 * "check".
 *
 * @extends Guarded<AvailabilityInterface>
 */
final class GuardedAvailability extends Guarded implements AvailabilityInterface
{
    public function __construct(AvailabilityInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function check(string $domain): Availability
    {
        return $this->guard->call('check', fn () => $this->provider->check($domain));
    }
}
