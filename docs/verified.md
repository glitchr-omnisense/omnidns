---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-26 in the [harness](harness.md), every package from its checkout, PHP 8.4, no
network: 68 tests, 222 assertions, green.

- The core: the models (names normalized, records relative to their zone, an MX's priority apart
  and back, the same record by id or by content, days left); the registry; the in-memory provider
  through every contract; in plain PHP, a registry used loading nothing but omnidns and Symfony's
  HTTP client contracts (`BareTest`); the installation page's script run as written (`DocsTest`).
- Each provider on recorded answers, every request checked (path, method, credentials - OVH's
  signature computed as OVH documents it -, body, paging, errors): OVH's availability (free,
  priced, taken with a transfer offered, not orderable, one cart kept), a registration as a cart
  checked out with its owner, a renewal through the services API, its domains, name servers and
  records; Gandi's free and taken domains, a registration with its owner, `Dry-Run` and
  `sharing_id`, a renewal, rrsets created, extended, changed, shrunk and deleted; Cloudflare's
  records page after page, filtered, an addition (MX, CAA as data), an update keeping the proxy,
  a deletion by id, the Registrar's domains; Scaleway's availability, a registration with a held
  contact and with one in full, the project required, a renewal, the records changed through the
  zone's PATCH; each provider's classes loading with its requirements alone.

The examples of this manual (records and names, the in-memory provider, the error messages)
were run in the harness on 2026-10-26.

## What is not verified

- **None of the four providers was run against a live account.** Every one needs an account and
  a key, OVH's availability included (its order cart answers "You must login first" to an
  anonymous question, checked 2026-10-24 per the README). The recorded answers are written from
  each API's reference, not captured: a first use against a real account is the check still
  owed, before any order costs money. OVH's renewal call (`/service/{id}/renew`) is the least
  sure of the shapes. Scaleway's API is a beta version (v2beta1): its shapes may change.
- The [Symfony](symfony.md) and [Laravel](laravel.md) pages were written from the classes'
  constructors, not run in an application.
- The known defects found while writing this manual (Cloudflare's domains without
  `account_id`, OVH's listed rights) are fixed since: the registry's getters ask the capabilities
  (with a test), OVH's rights name DELETE on `/domain/zone/*` (a list, no test).

## Responsibility

omnisense/omnidns and its omnidns/* providers are unofficial clients, affiliated with none of the
services they reach. The MIT licence covers the code only and grants no right to any service;
whoever runs them complies with each service's terms (named in each provider's README and in
`Capabilities::$terms`), pays for the orders made, and obtains what they require.
