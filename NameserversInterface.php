<?php

namespace Omnidns;

/** The name servers a registered domain delegates to. */
interface NameserversInterface extends ProviderInterface
{
    /** @return list<string> host names, no trailing dot */
    public function nameservers(string $domain): array;

    /** @param list<string> $nameservers */
    public function setNameservers(string $domain, array $nameservers): void;
}
