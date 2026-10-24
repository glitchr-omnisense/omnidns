<?php

namespace Omnidns\Testing;

use Omnidns\AvailabilityInterface;
use Omnidns\Capabilities;
use Omnidns\DnsInterface;
use Omnidns\DomainsInterface;
use Omnidns\Exception\ProviderException;
use Omnidns\Model\Availability;
use Omnidns\Model\Contact;
use Omnidns\Model\DomainInfo;
use Omnidns\Model\DomainName;
use Omnidns\Model\Money;
use Omnidns\Model\Order;
use Omnidns\Model\Record;
use Omnidns\NameserversInterface;
use Omnidns\RegistrarInterface;

/**
 * A registrar and a DNS host in memory, every contract answered: for tests,
 * demos and an application's fixtures. A domain registered is held for the
 * years asked, served by ns1/ns2.memory.test, its zone empty.
 */
final class MemoryProvider implements AvailabilityInterface, RegistrarInterface, DomainsInterface, NameserversInterface, DnsInterface
{
    /** @var array<string, DomainInfo> */
    private array $domains = [];

    /** @var array<string, list<Record>> */
    private array $zones = [];

    private int $ids = 0;

    /**
     * @param array<string, string> $domains the domains held, and when each ends ("2027-03-01")
     * @param list<string>          $taken   the domains held by someone else
     */
    public function __construct(array $domains = [], private readonly array $taken = [], private readonly ?Money $price = null, private readonly \DateTimeImmutable $now = new \DateTimeImmutable('2026-01-01'))
    {
        foreach ($domains as $name => $expires) {
            $this->hold(DomainName::normalize($name), new \DateTimeImmutable($expires));
        }
    }

    public function getName(): string
    {
        return 'memory';
    }

    public function getTitle(): string
    {
        return 'In memory';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(true, true, true, true, true, Record::TYPES);
    }

    public function check(string $domain): Availability
    {
        $domain = DomainName::normalize($domain);
        $free = !isset($this->domains[$domain]) && !\in_array($domain, $this->taken, true);

        return new Availability($domain, $free, $free ? $this->price : null, $free ? $this->price : null, reason: $free ? null : 'registered');
    }

    public function register(string $domain, int $years = 1, ?Contact $owner = null): Order
    {
        $domain = DomainName::normalize($domain);
        if (!$this->check($domain)->available) {
            throw ProviderException::answered('memory', "the registration of $domain", 'not available');
        }
        $this->hold($domain, $this->now->modify("+$years years"));

        return new Order($domain, Order::REGISTER, 'done', 'order-'.++$this->ids, $this->price?->multiply($years), years: $years);
    }

    public function renew(string $domain, int $years = 1): Order
    {
        $info = $this->domain($domain);
        $this->domains[$info->name] = new DomainInfo($info->name, $info->expiresAt?->modify("+$years years"), $info->autoRenew, $info->status, $info->nameservers, $info->createdAt);

        return new Order($info->name, Order::RENEW, 'done', 'order-'.++$this->ids, $this->price?->multiply($years), years: $years);
    }

    public function domains(): array
    {
        return array_keys($this->domains);
    }

    public function domain(string $domain): DomainInfo
    {
        return $this->domains[DomainName::normalize($domain)] ?? throw ProviderException::answered('memory', "the domain $domain", 'not in the account');
    }

    public function nameservers(string $domain): array
    {
        return $this->domain($domain)->nameservers ?? [];
    }

    public function setNameservers(string $domain, array $nameservers): void
    {
        $info = $this->domain($domain);
        $this->domains[$info->name] = new DomainInfo($info->name, $info->expiresAt, $info->autoRenew, $info->status, array_map(static fn (string $ns) => rtrim(strtolower($ns), '.'), $nameservers), $info->createdAt);
    }

    public function records(string $zone, ?string $type = null, ?string $name = null): array
    {
        $type = null === $type ? null : strtoupper($type);

        return array_values(array_filter($this->zone($zone), static fn (Record $r) => (null === $type || $r->type === $type) && (null === $name || $r->name === Record::normalizeName($name))));
    }

    public function add(string $zone, Record $record): Record
    {
        $zone = $this->zoneName($zone);
        $this->zones[$zone][] = $record = $record->withId((string) ++$this->ids);

        return $record;
    }

    public function update(string $zone, Record $current, Record $new): Record
    {
        $zone = $this->zoneName($zone);
        $found = $current->findIn($this->zone($zone)) ?? throw ProviderException::answered('memory', "a record of $zone", 'no such record');
        $new = $new->withId($found->id);
        $this->zones[$zone] = array_map(static fn (Record $r) => $r->sameAs($found) ? $new : $r, $this->zone($zone));

        return $new;
    }

    public function delete(string $zone, Record $record): void
    {
        $zone = $this->zoneName($zone);
        $found = $record->findIn($this->zone($zone)) ?? throw ProviderException::answered('memory', "a record of $zone", 'no such record');
        $this->zones[$zone] = array_values(array_filter($this->zone($zone), static fn (Record $r) => !$r->sameAs($found)));
    }

    private function hold(string $domain, \DateTimeImmutable $expires): void
    {
        $this->domains[$domain] = new DomainInfo($domain, $expires, true, ['ok'], ['ns1.memory.test', 'ns2.memory.test'], $this->now);
        $this->zones[$domain] ??= [];
    }

    /** @return list<Record> */
    private function zone(string $zone): array
    {
        return $this->zones[$this->zoneName($zone)];
    }

    private function zoneName(string $zone): string
    {
        $zone = DomainName::normalize($zone);

        return isset($this->zones[$zone]) ? $zone : throw ProviderException::answered('memory', "the zone $zone", 'not served here');
    }
}
