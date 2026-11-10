---
title: Usage policies
order: 45
---

# Usage policies

Each provider declares its usage policy as data: a `policy.json` beside its code, in
omnisense/contracts' schema (`resources/usage-policy.schema.json`), named in its `composer.json`
(`extra.omnisense.policy`), read by `UsagePolicy::fromFile()` and given by
`Capabilities::policy()`. It says the provider's rate limits, the rights its terms give, what every
request must carry, and where and when this was read (`provenance.verifiedAt`). What the terms do
not say is `null` (limits) or `unknown` (rights): no figure is guessed.

## Each provider's

Read on 2026-11-10.

| Provider | Rate limits | Rights | Source |
|---|---|---|---|
| `cloudflare` | 1,200 requests every 5 minutes, and 200 a second (counted for the whole application) | unknown; the agreement forbids circumventing usage limits and selling access to the Services | [API limits](https://developers.cloudflare.com/fundamentals/api/reference/limits/), [terms](https://www.cloudflare.com/terms/) |
| `gandi` | 1,000 requests a minute (counted for the whole application) | unknown | [API reference](https://api.gandi.net/docs/reference/), [terms](https://www.gandi.net/en/contracts/terms-of-service) |
| `ovh` | unknown: OVHcloud publishes none for this API (its documented limits are the Public Cloud OpenStack APIs') | unknown | [API first steps](https://docs.ovhcloud.com/en/guides/manage-and-operate/api/first-steps), [contracts](https://www.ovhcloud.com/en/terms-and-conditions/contracts/) |
| `scaleway` | unknown: none published for the Domains and DNS API | unknown | [API](https://www.scaleway.com/en/developers/api/domains-and-dns/), [contracts](https://www.scaleway.com/en/contracts/) |
| `memory` | none | - | - |

Cloudflare counts per user and Gandi per source address: the policy counts for the whole
application, the stricter form when one application runs several tokens or addresses. Every
provider needs a key.

```sh
vendor/bin/omnisense-policies    # every installed provider's policy, as one JSON
```

## The guard

The registry's typed getters give each provider behind its `PolicyGuard`
(`Policy\GuardedAvailability`, `GuardedRegistrar`, `GuardedDomains`, `GuardedNameservers`,
`GuardedDns`). Each call is an operation named after the contract's method - `check`,
`register`, `renew`, `domains`, `domain`, `nameservers`, `setNameservers`, `records`, `add`,
`update`, `delete` - checked against the limits that count it, then counted, failed or not. A call
over a limit is never made: `UsageLimitReachedException`, with `retryAfter`.

A 429 is read too: Gandi and Scaleway through `Json::request()` and the provider's `Backoff`;
Cloudflare and OVH, whose SDKs send with Guzzle, from the error response the SDK carries. Its
`Retry-After` (or a `RateLimit` field with nothing left) holds every call of that provider until
the wait is over, in every process sharing the limiter.

One operation may make several requests: `records()` follows the pages (Cloudflare, Scaleway),
OVH reads each record by its id. The guard counts the operation once; a site with large zones
declares its own, lower limit for `records`.

`get()` gives the provider itself, unguarded.

## A site's tightening

```php
$registry = new Registry([new CloudflareFactory()], [
    'dns' => [
        'factory' => 'cloudflare',
        'options' => ['token' => '...'],
        'policy' => ['rateLimits' => [['max' => 100, 'window' => 'minute', 'operations' => ['add', 'update', 'delete']]]],
    ],
], $limiter);

$registry->policy('dns');    // Cloudflare's, with the site's limit added
```

A site adds limits and makes permissions stricter, never looser: a limit counting the same calls
with a higher maximum, or `unknown` turned into `allowed`, is an `InvalidPolicyException` on the
first typed getter. `$limiter` is where every call is counted: an in-memory one by default, a
shared one (omnibridge/symfony's, over symfony/rate-limiter) when the application runs on many
processes.
