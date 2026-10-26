# omnisense/omnidns

A domain name where it is registered, under any PHP host: whether it is free and at what price,
registering and renewing it, the domains an account holds and when each ends, their name
servers, and the records of their zones - from OVH, Gandi, Cloudflare or Scaleway, through one
set of models. Plain PHP: no framework in `require`; a provider takes the application's HTTP
client, or wraps the official client where one is maintained.

```php
use Omnidns\Cloudflare\CloudflareFactory;
use Omnidns\Model\Record;
use Omnidns\Ovh\OvhFactory;
use Omnidns\Registry;

$registry = new Registry([new OvhFactory(), new CloudflareFactory()], [
    'ovh' => ['factory' => 'ovh', 'options' => ['application_key' => $ak, 'application_secret' => $as, 'consumer_key' => $ck]],
    'cloudflare' => ['factory' => 'cloudflare', 'options' => ['token' => $token]],
]);

$registry->availability('ovh')->check('example.org');              // Model\Availability: free, 6.99 EUR
$registry->registrar('ovh')->register('example.org', 1);           // Model\Order: awaiting payment at ->paymentUrl
$registry->domains('ovh')->domain('example.org')->daysLeft();      // 365
$registry->nameservers('ovh')->setNameservers('example.org', ['ada.ns.cloudflare.com', 'bob.ns.cloudflare.com']);
$registry->dns('cloudflare')->add('example.org', new Record('A', '', '192.0.2.10', 3600));
```

## The shape

- **Models** (`Omnidns\Model`): `Availability` (free or not, the first year's and the renewal's
  price, premium), `Order` (a registration or a renewal: its id, state, price, where to pay),
  `DomainInfo` (term, auto-renewal, statuses, name servers, `daysLeft()`), `Contact` (the
  registrant in full, or a handle the provider holds), `Record` (type, name relative to the zone,
  content, TTL, an MX's priority apart, the provider's id and options), `Money`, `DomainName`.
- **Contracts by capability**, each provider saying which it has (`Capabilities`, with the record
  types and the terms that apply): `AvailabilityInterface`, `RegistrarInterface`,
  `DomainsInterface`, `NameserversInterface`, `DnsInterface`. `Registry`, `Config`,
  `ProviderFactory`: providers by name, as in the other omni families.
- **Records as values**: a record is found by its id when the provider gives one, otherwise by
  its name, type and content (`Record::sameAs()`): Gandi groups records by name and type and
  names none; omnidns hides it.
- **Http\Json**: a JSON call on the application's client, failures as omnidns's exceptions
  (`UnreachableException`, `ProviderException` with the HTTP status as its code and the
  provider's message), typed reads of an answer.
- **Testing\MemoryProvider**: a registrar and a DNS host in memory, every contract answered.
- **Providers** are packages:

  | package | availability | register, renew | domains | name servers | records | client |
  |---|---|---|---|---|---|---|
  | [omnidns/ovh](https://github.com/glitchr-omnisense/omnidns-ovh) | yes, priced | yes (a cart) | yes | yes | yes | ovh/ovh (official) |
  | [omnidns/gandi](https://github.com/glitchr-omnisense/omnidns-gandi) | yes, priced | yes | yes | yes | LiveDNS | the application's |
  | [omnidns/cloudflare](https://github.com/glitchr-omnisense/omnidns-cloudflare) | - | - | Registrar, read | - | yes | cloudflare/sdk |
  | [omnidns/scaleway](https://github.com/glitchr-omnisense/omnidns-scaleway) | yes | yes | yes | yes | yes | the application's |

- **Hosts**: nothing of a framework here. The bridges (omnibridge/symfony, omnibridge/laravel)
  wire omnidns when the first site needs it (the forge, for its clients' domains).
- **Frontier**: reading a domain's public registration data (RDAP, WHOIS) is
  omnisense/omnistate's `omnistate/iana`, not this family.

Why a family and not `namingo/plexdns`, the one aggregated PHP library: it is the tool of a
registry and registrar platform (Namingo) - its own `zones` and `records` tables over PDO as the
source of truth, thirteen DNS providers and not OVH, neither purchase nor term - not a studio's
handling of its clients' domains where they are registered.

## Documentation

- [Installation, in plain PHP](docs/installation.md) (run as written by the tests)
- [Writing a provider](docs/providers.md)
- [The harness](docs/harness.md)

## Verified, and not

- Verified (`docker compose run --rm omnidns test` in `docker/`, PHP 8.4, 2026-10-24): the
  models (names normalized, records relative to their zone, an MX's priority apart and back, the
  same record by id or by content, days left); the registry; the in-memory provider through
  every contract; each provider on recorded answers, every request checked (path, method,
  credentials - OVH's signature computed as OVH documents it -, body, paging, errors); in plain
  PHP, a registry used loading nothing but omnidns and Symfony's HTTP client contracts
  (`BareTest`), and each provider's classes with its requirements alone; the installation page
  run as written (`DocsTest`). PHPStan at the highest level, no baseline, each package.
- **Not verified live: none of the four providers.** Every one needs an account and a key, OVH's
  availability included (its order cart answers "You must login first" to an anonymous
  question, checked 2026-10-24); no account was at hand. The recorded answers are written from
  each API's reference, not captured: a first use against a real account is the check still
  owed, before any order costs money.

## Responsibility

omnisense/omnidns and its omnidns/* providers are unofficial clients, affiliated with none of
the services they reach. The MIT licence covers the code only and grants no right to any service;
whoever runs them complies with each service's terms (named in each provider's README and in
`Capabilities::$terms`), pays for the orders made, and obtains what they require.

## Licence

MIT.
