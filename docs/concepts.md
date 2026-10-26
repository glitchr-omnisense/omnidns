---
title: Concepts
order: 20
---

# Concepts

## Provider and its contracts

`Omnidns\ProviderInterface` is one provider where domains are registered or their DNS served,
configured:

| Method | Returns |
|---|---|
| `getName(): string` | the factory's name: `ovh`, `gandi`, `cloudflare`, `scaleway`, `memory` |
| `getTitle(): string` | `OVHcloud`, `Gandi`, `Cloudflare`, `Scaleway`, `In memory` |
| `capabilities(): Capabilities` | what it can be asked, the record types its DNS takes, whose terms apply; calls nothing |

The contracts, each extending `ProviderInterface`:

| Interface | Methods |
|---|---|
| `AvailabilityInterface` | `check(string $domain): Availability` |
| `RegistrarInterface` | `register(string $domain, int $years = 1, ?Contact $owner = null): Order`, `renew(string $domain, int $years = 1): Order` |
| `DomainsInterface` | `domains(): list<string>`, `domain(string $domain): DomainInfo` |
| `NameserversInterface` | `nameservers(string $domain): list<string>` (host names, no trailing dot), `setNameservers(string $domain, list<string> $nameservers): void` |
| `DnsInterface` | `records(string $zone, ?string $type = null, ?string $name = null): list<Record>`, `add(string $zone, Record $record): Record`, `update(string $zone, Record $current, Record $new): Record`, `delete(string $zone, Record $record): void` |

`$owner` null in `register()` means the account's default contact, where the provider has one.
In `records()`, `$type` null is every type, `$name` (relative to the zone, `""` for the apex) null
every name. `add()` answers the record with its id when the provider gives one; `update()` the
record as it now is. `update()` and `delete()` take a record with or without an id: without one,
it is found among the zone's records by name, type and content (`Record::sameAs()`).

## Capabilities

`Omnidns\Capabilities` (readonly): `availability`, `registrar`, `domains`, `nameservers`, `dns`
(bool, default `false`), `recordTypes` (`list<string>`), `terms` (`list<string>`, URLs).
`contracts()` lists the contracts it has, `toArray()` gives `{contracts, recordTypes, terms}`.

| | availability | registrar | domains | nameservers | dns | terms |
|---|---|---|---|---|---|---|
| OVHcloud | yes | yes | yes | yes | yes | OVHcloud's contracts, the API's getting started rules |
| Gandi | yes | yes | yes | yes | yes | Gandi's terms of service, the API reference |
| Cloudflare | - | - | with `account_id` | - | yes | Cloudflare's terms, the API's limits |
| Scaleway | yes | yes | yes | yes | yes | Scaleway's contracts, the Domains and DNS API |
| `memory` | yes | yes | yes | yes | yes | - |

Every provider's `recordTypes` is `Record::TYPES`.

> [!WARNING]
> Known defect: `CloudflareProvider` implements `DomainsInterface` whatever its options; without
> `account_id` its `capabilities()->domains` is false, but `$registry->domains('cloudflare')` returns it and
> `domains()` throws `InvalidConfigException` (`The "cloudflare" provider needs an account_id to
> read the Registrar's domains.`), where its README says there is then no `DomainsInterface`
> (the registry's `NotSupportedException`).

## Factory and Config

`Omnidns\ProviderFactoryInterface`: `getName(): string`, `create(array $options = []):
ProviderInterface`. Every provider extends `Omnidns\ProviderFactory`:

1. `createConfig($options)`: the options in a `Config` (an `ArrayObject`), then the factory's
   `populate()` sets `omnidns.factory_name`, `omnidns.factory_title`,
   `omnidns.required_options` and the defaults of the others (`Config::defaults()` sets only keys
   not set yet);
2. the required options are checked: one missing, `''` or `[]` is an `InvalidConfigException`
   listing every missing key (`The "scaleway" provider needs: secret_key.`);
3. `build($config)` constructs the provider. Nothing is called.

`Config`'s readers: `get($key, $default)`, `string($key)` (`null` when empty), `int()`,
`float()`, `bool()` (a bool, or `"1"`, `"true"`, `"yes"`, `"on"`), `validateNotEmpty($keys)`.

## Registry

`Omnidns\Registry`:

```php
$registry = new Registry([new OvhFactory(), new CloudflareFactory()], [
    'registrar' => ['factory' => 'ovh', 'options' => ['application_key' => '...', 'application_secret' => '...', 'consumer_key' => '...']],
    'dns' => ['factory' => 'cloudflare', 'options' => ['token' => '...']],
]);
```

| Method | Returns |
|---|---|
| `get(string $name): ProviderInterface` | the provider, built the first time and kept |
| `availability()`, `registrar()`, `domains()`, `nameservers()`, `dns()` (each `string $name`) | the provider as that contract; `NotSupportedException` when it is not one, before any call (`The "cloudflare" DNS provider (Omnidns\Cloudflare\CloudflareProvider) does not check availability.`) |
| `has(string $name): bool` | configured or not |
| `options(string $name): array` | its configured options |
| `names(): list<string>` | the configured names |
| `factories(): list<string>` | the factories installed |

An unknown name or factory is an `InvalidConfigException` (`No "x" DNS provider; configured:
...`, `No "x" factory for the "y" DNS provider; installed: ...`).

## DomainName

`Omnidns\Model\DomainName::normalize(string $name): string` - lower case, trimmed, no trailing
dot; every provider normalizes the domains and zones it is given with it. A name longer than 253
characters, or whose labels a registry would refuse, is an `InvalidArgumentException`
(`"exa mple.org" is not a domain name (an internationalized one goes in its xn-- form).`): give an
internationalized name in its `xn--` form.

## Record

`Omnidns\Model\Record` (readonly), one DNS record of a zone:

| Property | Type | |
|---|---|---|
| `type` | string | one of `Record::TYPES`: A, AAAA, CAA, CNAME, MX, NS, SRV, TXT (upper-cased; another is an `InvalidArgumentException`) |
| `name` | string | relative to the zone, lower case, no trailing dot: `""` for the apex (`@` is read as it), `www`, `_dmarc` |
| `content` | string | an address, a host, a text; for an MX the host alone |
| `ttl` | `?int` | seconds; `null`: the zone's (or the provider's) default |
| `priority` | `?int` | an MX's priority, apart from its host |
| `id` | `?string` | the provider's id, when it gives one (not Gandi) |
| `options` | array | what one provider keeps besides: Cloudflare's `proxied` |

| Method | |
|---|---|
| `fromValue(string $type, string $name, string $value, ?int $ttl = null, ?string $id = null, array $options = []): self` | from a zone-file value: an MX's `"10 mx.example.net."` split into priority and host |
| `value(): string` | the zone-file value: the MX's priority before its host |
| `fqdn(string $zone): string` | `www.example.org`; the zone itself for the apex |
| `relative(string $name, string $zone): string` | a full name given by a provider (`www.example.org.`) as relative (`www`) |
| `normalizeName(string $name): string` | the name as records hold it |
| `sameAs(Record $other): bool` | the same id when both have one; else the same type, name and content - a host's case and trailing dot, and the quotes around a text, aside (a TXT's or CAA's case counts); an MX's priority compared when both have one |
| `findIn(iterable $records): ?Record` | the one that is `sameAs()` |
| `withId(?string $id): self` | |
| `toArray()` | |

```php
$mx = Record::fromValue('MX', '@', '10 mx.example.net.');
$mx->toArray();          // {"type":"MX","name":"","content":"mx.example.net.","ttl":null,"priority":10,"id":null,"options":[]}
$mx->value();            // "10 mx.example.net."
$mx->fqdn('example.org');                        // "example.org"
Record::relative('www.example.org.', 'example.org');   // "www"
$mx->sameAs(new Record('MX', '', 'MX.example.net', priority: 10));   // true
```

(run on 2026-10-26 in the [harness](harness.md))

## Availability

`Availability` (readonly): `domain`, `available` (bool), `price` (`?Money`, the first year's when
the provider quotes one: Gandi's before taxes, OVH's `TOTAL`), `renewal` (`?Money`, the renewal's
when the provider says: OVH), `premium` (bool), `reason` (`?string`, why it is not available:
`registered (a transfer is offered)`, `not orderable`, Gandi's status, `not available`, `not
offered`).

## Order

`Order` (readonly), what a registration or a renewal left with the provider: `domain`, `action`
(`Order::REGISTER` `register` or `Order::RENEW` `renew`), `status` (the provider's word, or the
package's: `ordered, awaiting payment`, `checked (dry run)`...), `id` (`?string`, the order or
task), `price` (`?Money`), `paymentUrl` (`?string`, where to pay when it waits for a payment),
`years` (int).

## DomainInfo

`DomainInfo` (readonly), a domain the account holds: `name`, `expiresAt`
(`?\DateTimeImmutable`), `autoRenew` (`?bool`), `status` (`list<string>`: the registry's or the
provider's statuses, `ok`, `clientTransferProhibited`...), `nameservers` (`?list<string>`, when
the provider gives them in the same answer; `null`: ask `NameserversInterface`), `createdAt`.
`daysLeft(?\DateTimeImmutable $now = null): ?int` - days before it ends, negative once it has,
`null` when the provider does not say (an end on 2027-03-01 is 126 days from 2026-10-26).

## Contact

`Contact` (readonly), the registrant: `firstName`, `lastName`, `email`, `phone` (in the
registries' form, `+33.123456789`), `street`, `city`, `zip`, `country` (ISO 3166-1 alpha-2), `type`
(`Contact::INDIVIDUAL` `individual`, `COMPANY` `company`, `ASSOCIATION` `association`),
`organization`, `handle`. `Contact::handle('<id>')` names a contact the provider already holds (an
OVH `/me/contact` id, a Scaleway contact id); the rest may then stay empty. What each provider
takes: [Domains](usage-domains.md#registering-and-renewing).

## Money

`Money` (readonly): `int $cents` (hundredths of the unit: 8.39 EUR is 839) and an ISO 4217
`currency`. `fromDecimal()`, `decimal()`, `multiply()`, `toArray()`; a currency that is not three
letters, or an amount that is not numeric, is an `InvalidConfigException`.

## Http\Json

`Omnidns\Http\Json::request($http, $method, $url, $options, $provider, $what)`: the call on the
application's client; a network failure is an `UnreachableException`; a status of 300 or more a
`ProviderException` whose message carries the provider's own words when it gives them (`message`,
`errors[0].message` or `error`) and whose code is the status (`Gandi answered 404 (The resource
could not be found.) for the domain example.org.`, code 404); an empty body is `[]`; another body
that is not JSON a `ProviderException`. The typed readers - `get()`, `string()`, `int()`,
`bool()`, `list()`, `date()` - walk an answer, `null` when a step is missing.
