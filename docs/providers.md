---
title: Writing a provider
order: 80
---

# Writing a provider

A provider is a package `omnidns/<name>` with a factory and a provider class:

- the factory extends `Omnidns\ProviderFactory`: `populate()` names it (`omnidns.factory_name`),
  lists its required options (`omnidns.required_options`) and the defaults of the others;
  `build()` returns the provider. The HTTP client (or the official client's adapter) comes
  through the factory's constructor, never created inside unless none is given.
- before writing a client, look for a maintained framework-free one and wrap it (OVH: ovh/ovh;
  Cloudflare: cloudflare/sdk); say in the README what was found.
- the provider implements the contracts it can answer - `AvailabilityInterface`,
  `RegistrarInterface`, `DomainsInterface`, `NameserversInterface`, `DnsInterface` - and says so
  in `capabilities()`, with the record types its DNS takes and the terms that apply.
- names are normalized with `DomainName::normalize()`; records hold a name relative to the zone
  (`Record::relative()` for a provider that gives full names), the MX priority apart
  (`Record::fromValue()` / `value()` for a provider that writes it in the value); a record type
  outside `Record::TYPES` is left out of `records()`.
- `update()` and `delete()` take a record with or without an id: without one, find it with
  `Record::findIn()` among `records($zone, $type, $name)`.
- every call through `Http\Json::request()` (or the wrapped client's errors turned into
  `ProviderException` / `UnreachableException`).
- tests run in the core's harness (`docker/harness/plugins.php` lists the providers), on
  recorded answers with each request checked, with a `BareTest`; a `LiveTest` (group `live`)
  only where the provider can be read without an account.

## What the four providers keep to

- Every domain and zone given goes through `DomainName::normalize()` before any call.
- A duration outside 1 to 10 years, and an owner the provider cannot take, are refused before
  any call (`InvalidArgumentException`).
- `update()` and `delete()` given a record without an id read the zone's records of that name
  and type and take the one that is `sameAs()`; none found is a `ProviderException` naming the
  zone.
- A change the provider cannot make in place (a record's type at OVH, a record's name or type in
  a Gandi rrset) is a deletion and an addition.
- Host names (name servers, records' targets compared) are lower-cased, their trailing dot
  removed where omnidns returns them.
- An order costs money: a provider that can check one without making it says so (Gandi's
  `dry_run`), and its README says how an order is paid.

## Registering it

- Plain PHP: give the factory to the `Registry`.
- The harness: a line in `docker/harness/plugins.php` (slug and tests namespace); the provider's
  checkout beside the core, or its repository at `github.com/glitchr-omnisense/omnidns-<slug>`.
