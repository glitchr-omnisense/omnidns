<?php

namespace Omnidns\Model;

/**
 * What a registration or a renewal left with the provider: its order or
 * task, its state, its price and, when it waits for a payment, where to pay.
 */
final readonly class Order
{
    public const REGISTER = 'register';
    public const RENEW = 'renew';

    public function __construct(
        public string $domain,
        public string $action,
        public string $status,
        public ?string $id = null,
        public ?Money $price = null,
        public ?string $paymentUrl = null,
        public int $years = 1,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['domain' => $this->domain, 'action' => $this->action, 'status' => $this->status, 'id' => $this->id, 'price' => $this->price?->toArray(), 'paymentUrl' => $this->paymentUrl, 'years' => $this->years];
    }
}
