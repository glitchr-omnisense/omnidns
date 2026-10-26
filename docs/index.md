# omnisense/omnidns

Omnidns handles domains where they are registered and served, under any PHP host: whether a
domain is free and at what price, registering and renewing it, the domains an account holds and
when each ends, their name servers, and the records of their zones - at OVHcloud, Gandi,
Cloudflare or Scaleway, through one set of models.

```php
$registry->availability('ovh')->check('example.fr');                 // Model\Availability: free, 8.39 EUR
$registry->registrar('gandi')->register('example.fr', 2, $owner);    // Model\Order
$registry->domains('scaleway')->domain('example.fr')->daysLeft();    // 126
$registry->nameservers('ovh')->setNameservers('example.fr', ['ns1.example.net', 'ns2.example.net']);
$registry->dns('cloudflare')->add('example.fr', new Record('A', 'www', '192.0.2.10'));
```

Each provider implements the **contracts** it can answer - `AvailabilityInterface`,
`RegistrarInterface`, `DomainsInterface`, `NameserversInterface`, `DnsInterface` - and says so,
with the record types its DNS takes and whose terms apply, in its `Capabilities`. A record is
found by its id when the provider gives one, otherwise by its name, type and content: Gandi
groups records by name and type and names none, and omnidns hides it. The design is the
Omnisense families' one: a provider's **factory** builds it from options, a **registry** holds
the application's providers by name.

The core is plain PHP: it requires `symfony/http-client-contracts` (interfaces only) and nothing
else. Gandi and Scaleway take the application's HTTP client; OVH and Cloudflare wrap their
official or community clients (ovh/ovh, cloudflare/sdk), which send with Guzzle.

Published as `omnisense/omnidns`, formerly `omniscient/omnidns`: the package `replace`s the old
name. The namespace is `Omnidns\`; providers keep the `omnidns/` vendor and the
`Omnidns\<Provider>\` namespace.

## When to use it

- A studio or a hosting back office that manages its clients' domains at several registrars:
  their terms, their renewals, their name servers.
- An application that writes DNS records (a client's subdomain, a TXT for a verification, an MX)
  wherever the zone is served.
- A domain search with prices, before an order.

It does not read a domain's public registration data (RDAP, WHOIS): that is
omnisense/omnistate's `omnistate/iana`. It keeps nothing: no cache, no zone of its own. It is not
`namingo/plexdns`, a registry and registrar platform's library with its own tables as the source
of truth.

> [!CAUTION]
> `register()` and `renew()` are purchases, made in the account's name. Each provider's page
> says whether an order is paid at once or waits for a payment at `Order::$paymentUrl`; Gandi's
> `dry_run` has Gandi check an order without making it. No provider was run against a live
> account ([Verified, and not](verified.md)).

## Providers

| Package | Factory name | Availability | Register, renew | Domains | Name servers | Records | Client |
|---|---|---|---|---|---|---|---|
| [`omnidns/ovh`](https://github.com/glitchr-omnisense/omnidns-ovh) | `ovh` | yes, priced | yes (an order cart) | yes | yes | yes | ovh/ovh (official), Guzzle |
| [`omnidns/gandi`](https://github.com/glitchr-omnisense/omnidns-gandi) | `gandi` | yes, priced | yes | yes | yes | LiveDNS | the application's |
| [`omnidns/cloudflare`](https://github.com/glitchr-omnisense/omnidns-cloudflare) | `cloudflare` | - | - | the Registrar's, read (with `account_id`) | - | yes | cloudflare/sdk, Guzzle |
| [`omnidns/scaleway`](https://github.com/glitchr-omnisense/omnidns-scaleway) | `scaleway` | yes, no price | yes | yes | yes | yes | the application's |
| `Omnidns\Testing\MemoryFactory` (in the core) | `memory` | yes | yes | yes | yes | yes | none |

The contracts are what each provider class implements and its `capabilities()` says (probed in
the [harness](harness.md) on 2026-10-26). Every DNS takes the eight types of `Record::TYPES`: A,
AAAA, CAA, CNAME, MX, NS, SRV, TXT; a provider's other types (OVH's SPF and DKIM, Gandi's ALIAS,
Cloudflare's HTTPS...) are left out of `records()`.

A registrar and a DNS host may be two providers: the domain bought at OVH, its zone served by
Cloudflare - two entries of the registry; `setNameservers()` at the first points the domain at the
second.

## How this manual is organised

- [Installation](installation.md): requirements, a first script in plain PHP (run as written by
  the tests).
- [Symfony](symfony.md) and [Laravel](laravel.md): no bridge yet; the registry as a service, by
  hand.
- [Concepts](concepts.md): providers and their contracts, capabilities, factories, the registry,
  every model.
- Usage: [domains](usage-domains.md) (availability, orders, terms, name servers) and
  [DNS records](usage-dns.md).
- [Configuration reference](configuration.md): every provider's options.
- [Errors](errors.md).
- [Testing](testing.md): the in-memory provider, recorded answers.
- [The Docker harness](harness.md).
- [Writing a provider](providers.md).
- [Verified, and not](verified.md).
