---
title: Domains
order: 31
---

# Domains

## Is it free?

```php
$check = $registry->availability('gandi')->check('example.fr');
if ($check->available) {
    echo $check->price?->decimal(), ' ', $check->price?->currency, $check->premium ? ' (premium)' : '', "\n";
} else {
    echo 'taken: ', $check->reason, "\n";
}
```

| | Call | Price | Not available |
|---|---|---|---|
| OVHcloud | the domain's offers in an order cart (`GET /order/cart/{id}/domain`; one cart opened per provider object, with `subsidiary`, and kept) | the `create` offer's `TOTAL` (else `PRICE`), the renewal its `RENEW`; premium when its pricing mode says | a `transfer` offer: `registered (a transfer is offered)`; else `not orderable` |
| Gandi | `GET /domain/check?name=` | the 1-year price before taxes, in the answer's currency (EUR when it says none) | Gandi's status as the reason; premium from the status or the process |
| Scaleway | `GET /search-domains`, strict | none quoted | `not available`; a domain not in the answer `not offered` |
| Cloudflare | - (`NotSupportedException` from the registry) | | |

OVH answers its cart's offers to a signed call only: availability needs the account's keys
there too.

## Registering and renewing

```php
use Omnidns\Model\Contact;

$owner = new Contact('Camille', 'Durand', 'camille@example.org', '+33.612345678', '1 rue de Rivoli', 'Paris', '75001', 'FR');
$order = $registry->registrar('gandi')->register('example.fr', 2, $owner);
echo $order->status, ' ', $order->id, ' ', $order->paymentUrl, "\n";

$registry->registrar('gandi')->renew('example.fr', 1);
```

Both are purchases. The years go from 1 to 10 at OVH, Gandi and Scaleway (another number is an
`InvalidArgumentException` before any call).

| | Register | Owner | Paid | Renew |
|---|---|---|---|---|
| OVHcloud | a new cart, assigned to the account, the domain added for `P<years>Y`, the owner set, checked out | a contact the account holds: `Contact::handle('<id from /me/contact>')`; none: the account's own; a contact in full is an `InvalidArgumentException` | `auto_pay: false` (the default): the order waits at `Order::$paymentUrl`, status `ordered, awaiting payment`; `true`: paid at once with the preferred means, `ordered, paid with the preferred means of payment` | the service id from `/domain/{name}/serviceInfos`, then `POST /service/{id}/renew` |
| Gandi | `POST /domain/domains` | in full (names, e-mail, phone, address, country, type; an empty e-mail or none is an `InvalidArgumentException`) | Gandi bills the account's prepaid balance or means of payment; status Gandi's message, else `accepted` | `POST /domain/domains/{name}/renew` |
| Scaleway | `POST /buy-domains` into `project_id` (required, else `InvalidConfigException`) | a contact Scaleway holds (`Contact::handle()`) or in full (`company` is Scaleway's `corporate`); none is an `InvalidArgumentException` | billed to the organization; status `ordered`, the task id as `id` | `POST /renew-domains` |

Gandi's `dry_run: true` sends `Dry-Run: 1` on registrations and renewals: Gandi checks the order
and makes nothing, and the order's status is `checked (dry run)`. The other providers have no dry
run: try an order first in a sandbox (Gandi's `endpoint`), or not at all.

OVH's order carries its `orderId`, its price with tax and its `url`; Gandi's and Scaleway's
carry no price.

## The account's domains

```php
foreach ($registry->domains('ovh')->domains() as $name) {
    $info = $registry->domains('ovh')->domain($name);
    printf("%s ends %s (%d days)%s\n", $name, $info->expiresAt?->format('Y-m-d'), $info->daysLeft(), $info->autoRenew ? ', renews itself' : '');
}
```

| | `domains()` | `domain()`: end, auto-renewal, statuses, name servers, creation |
|---|---|---|
| OVHcloud | `GET /domain` | `/domain/{name}/serviceInfos`: `expiration`, `renew.automatic`, the service's `status`, -, `creation` |
| Gandi | `GET /domain/domains`, 100 a page | `registry_ends_at`, `autorenew`, `status`, `nameservers`, `registry_created_at` |
| Scaleway | `GET /registrar/domains`, 100 a page, narrowed to `project_id` when set | `expired_at`, `auto_renew_status` (`enabled` or `enabling` is true), `status`, the apex zone's `ns`, - |
| Cloudflare | `accounts/{account_id}/registrar/domains` | `expires_at`, `auto_renew`, `registry_statuses` (split on commas), -, `created_at` |

## Name servers

```php
$registry->nameservers('ovh')->setNameservers('example.fr', ['ns1.example.net', 'ns2.example.net']);
$registry->nameservers('ovh')->nameservers('example.fr');   // ["ns1.example.net", "ns2.example.net"]
```

Host names are lower-cased and their trailing dot removed, both ways.

| | Read | Write |
|---|---|---|
| OVHcloud | `/domain/{name}/nameServer` (ids), then each id's `host` | `POST /domain/{name}/nameServers/update` |
| Gandi | `GET /domain/domains/{name}/nameservers` | `PUT` the same |
| Scaleway | `GET /dns-zones/{name}/nameservers` | `PUT` the same |
| Cloudflare | - (a zone's name servers are Cloudflare's own) | - |
