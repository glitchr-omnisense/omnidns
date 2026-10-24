<?php

namespace Omnidns;

use Omnidns\Model\DomainInfo;

/** The domains the account holds, and each one's term. */
interface DomainsInterface extends ProviderInterface
{
    /** @return list<string> */
    public function domains(): array;

    public function domain(string $domain): DomainInfo;
}
