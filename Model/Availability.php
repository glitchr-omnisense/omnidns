<?php

namespace Omnidns\Model;

/**
 * Whether a domain can be registered, and at what price when the provider
 * quotes one: the first year's (Gandi: before taxes; OVH: its "TOTAL"), and
 * the renewal's when it says.
 */
final readonly class Availability
{
    public function __construct(
        public string $domain,
        public bool $available,
        public ?Money $price = null,
        public ?Money $renewal = null,
        public bool $premium = false,
        public ?string $reason = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['domain' => $this->domain, 'available' => $this->available, 'price' => $this->price?->toArray(), 'renewal' => $this->renewal?->toArray(), 'premium' => $this->premium, 'reason' => $this->reason];
    }
}
