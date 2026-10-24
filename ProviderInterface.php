<?php

namespace Omnidns;

/**
 * One provider where domains are registered or their DNS is served,
 * configured: OVH, Gandi, Cloudflare, Scaleway... Its Capabilities say what
 * it can be asked - is a domain free (AvailabilityInterface), register or
 * renew it (RegistrarInterface), the domains the account holds and when they
 * end (DomainsInterface), their name servers (NameserversInterface), the
 * records of a zone (DnsInterface) - and whose terms apply.
 */
interface ProviderInterface
{
    /** The factory's name: "ovh", "gandi", "cloudflare", "scaleway"... */
    public function getName(): string;

    public function getTitle(): string;

    public function capabilities(): Capabilities;
}
