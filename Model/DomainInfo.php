<?php

namespace Omnidns\Model;

/**
 * A domain the account holds, as its registrar shows it: when it ends,
 * whether it renews itself, its statuses, its name servers when the
 * provider gives them in the same answer (null: ask NameserversInterface).
 */
final readonly class DomainInfo
{
    /**
     * @param list<string>      $status      the registry's or the provider's statuses: "ok", "clientTransferProhibited"...
     * @param list<string>|null $nameservers
     */
    public function __construct(
        public string $name,
        public ?\DateTimeImmutable $expiresAt = null,
        public ?bool $autoRenew = null,
        public array $status = [],
        public ?array $nameservers = null,
        public ?\DateTimeImmutable $createdAt = null,
    ) {
    }

    /** Days left before it ends (negative once it has); null when the provider does not say. */
    public function daysLeft(?\DateTimeImmutable $now = null): ?int
    {
        if (null === $this->expiresAt) {
            return null;
        }
        $days = (int) ($now ?? new \DateTimeImmutable())->diff($this->expiresAt)->days;

        return $this->expiresAt < ($now ?? new \DateTimeImmutable()) ? -$days : $days;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'expiresAt' => $this->expiresAt?->format(\DATE_ATOM), 'autoRenew' => $this->autoRenew, 'status' => $this->status, 'nameservers' => $this->nameservers, 'createdAt' => $this->createdAt?->format(\DATE_ATOM)];
    }
}
