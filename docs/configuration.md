---
title: Configuration reference
order: 40
---

# Configuration reference

## The registry

```php
new Registry(iterable $factories, array $config);
```

| Key | Type | Required | |
|---|---|---|---|
| `<name>` | string | - | the name the application gives the provider: `registrar`, `dns`... |
| `<name>.factory` | string | yes | `ovh`, `gandi`, `cloudflare`, `scaleway`, `memory` |
| `<name>.options` | array | no (`[]`) | the provider's options, below |

Options are taken as given: a required option given as `null`, `''` or `[]` is reported missing;
an optional one given as `''` reads as not set. Booleans accept `true`, `"1"`, `"true"`, `"yes"`,
`"on"` (any case), so an environment variable can be passed as it is.

## ovh

`Omnidns\Ovh\OvhFactory(?GuzzleHttp\Client $http = null)` - ovh/ovh signs every call and sends it
with that client, or its own.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `application_key` | string | - | yes | the API application's key |
| `application_secret` | string | - | yes | its secret |
| `consumer_key` | string | - | yes | the consumer key the account validated for the rights below |
| `endpoint` | string | `ovh-eu` | no | `ovh-eu`, `ovh-ca`, `ovh-us`, `kimsufi-eu`... (ovh/ovh's names), or a URL |
| `subsidiary` | string | `FR` | no | the OVHcloud company carts are opened with: `FR`, `DE`, `GB`, `CA`... |
| `auto_pay` | bool | `false` | no | `true`: an order is paid at once with the account's preferred means of payment; `false`: it waits at `Order::$paymentUrl` |

The application is created at https://eu.api.ovh.com/createToken/ with the rights the factory
lists: GET/POST on `/order/cart*`, GET/PUT/POST on `/domain*`, DELETE on `/domain/zone/*` (`delete()`,
and an `update()` that changes a record's type), GET/POST on `/service/*`.

## gandi

`Omnidns\Gandi\GandiFactory(HttpClientInterface $http)`

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `token` | string | - | yes | a personal access token, sent as `Authorization: Bearer`, with the rights needed: "See and renew domain names", "Manage domain name technical configurations" (LiveDNS), "Register domain names" for a registration |
| `sharing_id` | string | none | no | the organization billed and owning, when it is not the token's own; added to every `/domain/` call |
| `endpoint` | string | `https://api.gandi.net/v5` | no | Gandi's sandbox: `https://api.sandbox.gandi.net/v5` |
| `dry_run` | bool | `false` | no | `true`: a registration or a renewal is checked by Gandi and not made (`Dry-Run: 1`) |

## cloudflare

`Omnidns\Cloudflare\CloudflareFactory(?Cloudflare\API\Adapter\Adapter $adapter = null)` - none:
cloudflare/sdk's Guzzle adapter on the token.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `token` | string | - | yes | an API token with Zone:Read and DNS:Edit on the zones it manages, and Account Registrar:Read for the Registrar's domains |
| `account_id` | string | none | no | the account whose Registrar domains are read; none: `capabilities()->domains` is false and `domains()` throws |
| `base_uri` | string | `https://api.cloudflare.com/client/v4/` | no | given to the SDK's Guzzle adapter (not used with an adapter of the application's) |

## scaleway

`Omnidns\Scaleway\ScalewayFactory(HttpClientInterface $http)`

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `secret_key` | string | - | yes | an API key's secret, sent as `X-Auth-Token`, with DomainsDNSFullAccess (or DomainsDNSReadOnly to read) |
| `project_id` | string | none | to register | the project domains are bought into; it also narrows the domains listed |
| `endpoint` | string | `https://api.scaleway.com/domain/v2beta1` | no | |

## memory

`Omnidns\Testing\MemoryFactory()` ([Testing](testing.md)).

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `domains` | `array<string, string>` | `[]` | no | the domains held and when each ends: `['example.org' => '2027-03-01']` |
| `taken` | `list<string>` | `[]` | no | the domains held by someone else |
| `price` | `Model\Money` | none | no | a year's price: availability's price and renewal, and each order's (times the years) |
