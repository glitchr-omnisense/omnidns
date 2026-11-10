<?php

namespace Omnidns;

use Omnisense\Contracts\Policy\UsagePolicy;
use Omnisense\Contracts\Provider\CapabilitiesInterface;

/**
 * What a provider can be asked, said before it is asked anything, and its
 * usage policy: its rate limits, whose terms apply.
 *
 * The policy is omnisense/contracts' UsagePolicy. $terms, written before
 * it, stays, read from the policy when it is given and $terms is not: a
 * provider that gives $terms alone gets a policy made of them, its limits
 * unknown (UsagePolicy::described()).
 */
final readonly class Capabilities implements CapabilitiesInterface
{
    /**
     * @var list<string>
     *
     * @deprecated since 1.x, read policy()->provenance (terms, policy)
     */
    public array $terms;

    private UsagePolicy $policy;

    /**
     * @param bool         $availability whether a domain is free, its price (AvailabilityInterface)
     * @param bool         $registrar    registering and renewing (RegistrarInterface)
     * @param bool         $domains      the account's domains and their terms (DomainsInterface)
     * @param bool         $nameservers  a domain's name servers (NameserversInterface)
     * @param bool         $dns          a zone's records (DnsInterface)
     * @param list<string> $recordTypes  the record types its DNS takes
     * @param list<string> $terms        the terms whoever runs it must follow (URLs)
     * @param ?UsagePolicy $policy       its usage policy; made of $terms when null (a key required: every omnidns provider is an account's)
     */
    public function __construct(
        public bool $availability = false,
        public bool $registrar = false,
        public bool $domains = false,
        public bool $nameservers = false,
        public bool $dns = false,
        public array $recordTypes = [],
        array $terms = [],
        ?UsagePolicy $policy = null,
    ) {
        $this->policy = $policy ?? UsagePolicy::described(UsagePolicy::UNNAMED, null, null, $terms, false);
        $this->terms = [] !== $terms || null === $policy ? $terms : array_values(array_unique(array_filter([$policy->provenance->terms, $policy->provenance->policy])));
    }

    public function policy(): UsagePolicy
    {
        return $this->policy;
    }

    /** @return list<string> what it can be asked, by contract */
    public function contracts(): array
    {
        return array_keys(array_filter(['availability' => $this->availability, 'registrar' => $this->registrar, 'domains' => $this->domains, 'nameservers' => $this->nameservers, 'dns' => $this->dns]));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['contracts' => $this->contracts(), 'recordTypes' => $this->recordTypes, 'terms' => $this->terms, 'policy' => $this->policy->toArray()];
    }
}
