<?php

namespace Omnidns\Policy;

use Omnidns\DnsInterface;
use Omnidns\Model\Record;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's zone records behind its guard: each call is the operation
 * named after its method, "records", "add", "update" or "delete".
 *
 * @extends Guarded<DnsInterface>
 */
final class GuardedDns extends Guarded implements DnsInterface
{
    public function __construct(DnsInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function records(string $zone, ?string $type = null, ?string $name = null): array
    {
        return $this->guard->call('records', fn () => $this->provider->records($zone, $type, $name));
    }

    public function add(string $zone, Record $record): Record
    {
        return $this->guard->call('add', fn () => $this->provider->add($zone, $record));
    }

    public function update(string $zone, Record $current, Record $new): Record
    {
        return $this->guard->call('update', fn () => $this->provider->update($zone, $current, $new));
    }

    public function delete(string $zone, Record $record): void
    {
        $this->guard->call('delete', fn () => $this->provider->delete($zone, $record));
    }
}
