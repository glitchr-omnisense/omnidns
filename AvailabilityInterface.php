<?php

namespace Omnidns;

use Omnidns\Model\Availability;

/** Whether a domain can be registered here, and at what price. */
interface AvailabilityInterface extends ProviderInterface
{
    public function check(string $domain): Availability;
}
