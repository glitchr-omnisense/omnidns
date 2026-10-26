---
title: The Docker harness
order: 70
---

# The Docker harness

`docker/` composes this core and every provider (`docker/harness/plugins.php`) into one
installation, PHP 8.4, in the image `omnisense/omnidns-harness`. `docker/harness/setup.php`
writes the harness's `composer.json` and `phpunit.xml` into a volume, and the entrypoint installs
them when they change.

```sh
cd docker
docker compose run --rm omnidns test       # every package's tests, no network (the live group left out)
docker compose run --rm omnidns bare       # the bare script: plain PHP, and what PHP loaded
docker compose run --rm omnidns phpstan    # PHPStan at the highest level, each package
docker compose run --rm -v "$PWD/scratch:/scratch" omnidns php /scratch/x.php   # a script of your own (autoloader: /harness/vendor/autoload.php)
docker compose down -v                     # the harness's volume gone
```

With no command the entrypoint runs `test`; `live` runs the `live` group (no provider has one:
none can be read without an account); `composer`, `sh` and `php` run as they are; anything else
exits 2 with the list. The service uses Docker's default bridge (`network_mode: bridge`).

Providers come from GitHub (branch 1.x) unless `OMNIDNS_PLUGINS` (in `docker/.env`) points at a
directory holding their checkouts - `../..` when this core sits in the workspace beside them, as
`docker/.env` sets it.

## Bare

`docker/harness/bin/bare` uses Omnidns in plain PHP - no bundle, no container, no framework: a
registry with the in-memory provider; a domain checked, one taken, one registered for two years
and renewed, its name servers changed, its zone written (an A, an MX, a TXT; the A updated, the
TXT deleted); a provider whose factory is not installed. It prints what happened and every class
and file PHP loaded, as JSON; run on 2026-10-26, without the `loaded` part:

```
"free": {"domain": "omnidns-bare.test", "available": true, "price": {"cents": 839, "currency": "EUR", "decimal": "8.39"}, ...},
"taken": false,
"order": {"domain": "omnidns-bare.test", "action": "register", "status": "done", "id": "order-1", "price": {"cents": 1678, ...}, "years": 2},
"domains": ["glitchr.test", "omnidns-bare.test"],
"expires": "2029-01-01",
"nameservers": ["ns1.example.net", "ns2.example.net"],
"records": [["A", "omnidns-bare.test", "192.0.2.2", 300], ["MX", "omnidns-bare.test", "10 mx.example.net.", null]],
"missing": "No \"missing\" factory for the \"nowhere\" DNS provider; installed: memory."
```

`Tests/BareTest.php` runs it in a process of its own and checks that nothing outside the package
was loaded but Symfony's HTTP client contracts. Each provider's `BareTest` loads every one of its
classes with its required packages alone.
