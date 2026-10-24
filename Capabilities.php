<?php

namespace Omnidns;

/** What a provider can be asked, said before it is asked anything, and whose terms apply. */
final readonly class Capabilities
{
    /**
     * @param bool         $availability whether a domain is free, its price (AvailabilityInterface)
     * @param bool         $registrar    registering and renewing (RegistrarInterface)
     * @param bool         $domains      the account's domains and their terms (DomainsInterface)
     * @param bool         $nameservers  a domain's name servers (NameserversInterface)
     * @param bool         $dns          a zone's records (DnsInterface)
     * @param list<string> $recordTypes  the record types its DNS takes
     * @param list<string> $terms        the terms whoever runs it must follow (URLs)
     */
    public function __construct(
        public bool $availability = false,
        public bool $registrar = false,
        public bool $domains = false,
        public bool $nameservers = false,
        public bool $dns = false,
        public array $recordTypes = [],
        public array $terms = [],
    ) {
    }

    /** @return list<string> what it can be asked, by contract */
    public function contracts(): array
    {
        return array_keys(array_filter(['availability' => $this->availability, 'registrar' => $this->registrar, 'domains' => $this->domains, 'nameservers' => $this->nameservers, 'dns' => $this->dns]));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['contracts' => $this->contracts(), 'recordTypes' => $this->recordTypes, 'terms' => $this->terms];
    }
}
