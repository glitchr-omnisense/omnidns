<?php

namespace Omnidns;

use Omnidns\Model\Record;

/**
 * The records of a zone the provider serves. A record is found by its id
 * when the provider gives one, else by its name, type and content
 * (Record::sameAs()).
 */
interface DnsInterface extends ProviderInterface
{
    /**
     * @param string|null $type a record type ("MX"); null: every type
     * @param string|null $name a name relative to the zone ("" for the apex); null: every name
     *
     * @return list<Record>
     */
    public function records(string $zone, ?string $type = null, ?string $name = null): array;

    /** @return Record the record added, with its id when the provider gives one */
    public function add(string $zone, Record $record): Record;

    /** @return Record the record as it now is */
    public function update(string $zone, Record $current, Record $new): Record;

    public function delete(string $zone, Record $record): void;
}
