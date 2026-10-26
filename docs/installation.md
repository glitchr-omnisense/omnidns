---
title: Installation
order: 10
---

# Installation

```sh
composer require omnisense/omnidns omnidns/gandi symfony/http-client
```

Each provider is its own package: `omnidns/ovh`, `omnidns/gandi`, `omnidns/cloudflare`,
`omnidns/scaleway`.

## Requirements

- PHP 8.2 or later.
- `omnisense/omnidns` requires `symfony/http-client-contracts` (^3.0), interfaces only, and
  nothing else.
- `omnidns/gandi` and `omnidns/scaleway` require `omnisense/omnidns` (1.x) and
  `symfony/http-client-contracts`: the application gives the HTTP client (`symfony/http-client`,
  or any implementation of the contracts) to the factory's constructor.
- `omnidns/ovh` requires `ovh/ovh` (^3.4, OVHcloud's official client) and `guzzlehttp/guzzle`
  (^7.0); `ovh/ovh` brings `league/oauth2-client`.
- `omnidns/cloudflare` requires `cloudflare/sdk` (^1.4 or ^2.0) and `psr/http-message`; the SDK
  sends with Guzzle.
- `ext-json` (always present since PHP 8.0).

## Plain PHP

The block below runs as written in the tests (`Tests/DocsTest.php`, which checks the printed
lines against the comments), on the in-memory provider omnidns ships for tests and demos; in
production its factory is a provider's (`new GandiFactory($httpClient)` with `['token' =>
...]`), the calls the same.

```php
<?php

require 'vendor/autoload.php';

use Omnidns\Model\Money;
use Omnidns\Model\Record;
use Omnidns\Registry;
use Omnidns\Testing\MemoryFactory;

$registry = new Registry([new MemoryFactory()], [
    'registrar' => ['factory' => 'memory', 'options' => ['taken' => ['example.org'], 'price' => Money::fromDecimal('8.39', 'EUR')]],
]);

$check = $registry->availability('registrar')->check('glitchr-example.org');
echo $check->domain, ': ', $check->available ? 'free, '.$check->price?->decimal().' '.$check->price?->currency : 'taken', "\n";
// glitchr-example.org: free, 8.39 EUR

$order = $registry->registrar('registrar')->register('glitchr-example.org', 2);
echo $order->action, ' ', $order->status, ', ends ', $registry->domains('registrar')->domain('glitchr-example.org')->expiresAt?->format('Y-m-d'), "\n";
// register done, ends 2028-01-01

$dns = $registry->dns('registrar');
$dns->add('glitchr-example.org', new Record('A', '', '192.0.2.10', 3600));
$dns->add('glitchr-example.org', Record::fromValue('MX', '', '10 mx.example.net.'));
foreach ($dns->records('glitchr-example.org') as $record) {
    echo $record->fqdn('glitchr-example.org'), ' ', $record->type, ' ', $record->value(), "\n";
}
// glitchr-example.org A 192.0.2.10
// glitchr-example.org MX 10 mx.example.net.
```

A registrar and a DNS host may be two providers: the domain bought at OVH, its zone served by
Cloudflare - two entries of the registry, `registrar('ovh')` and `dns('cloudflare')`.
`setNameservers()` at the first points the domain at the second.

Registering and renewing cost money. Each provider's README says whether an order is paid at
once or waits for a payment at `Order::$paymentUrl`; Gandi's `dry_run` has Gandi check an order
without making it.

Building a provider calls nothing: a provider is built the first time the registry is asked for
it, its required options checked then (`The "gandi" provider needs: token.`); a platform is
called only by the contracts' methods.

No class of a framework is loaded on the way: `Tests/BareTest.php` runs the harness's bare
script in a process of its own and checks that nothing but the package and the HTTP client
contracts was loaded ([harness](harness.md)).

## A real provider

```php
use Omnidns\Gandi\GandiFactory;
use Omnidns\Ovh\OvhFactory;
use Omnidns\Registry;
use Symfony\Component\HttpClient\HttpClient;

$registry = new Registry([new GandiFactory(HttpClient::create(['timeout' => 20])), new OvhFactory()], [
    'gandi' => ['factory' => 'gandi', 'options' => ['token' => getenv('GANDI_TOKEN'), 'dry_run' => true]],
    'ovh' => ['factory' => 'ovh', 'options' => [
        'application_key' => getenv('OVH_APPLICATION_KEY'),
        'application_secret' => getenv('OVH_APPLICATION_SECRET'),
        'consumer_key' => getenv('OVH_CONSUMER_KEY'),
    ]],
]);

$registry->availability('ovh')->check('example.fr');
$registry->dns('gandi')->records('example.fr', 'MX');
```

`OvhFactory` takes a `GuzzleHttp\Client` (or none: ovh/ovh makes its own), `CloudflareFactory` a
`Cloudflare\API\Adapter\Adapter` (or none: the SDK's Guzzle adapter on the token).

## In a framework

There is no Omnidns bundle and no provider in omnibridge/symfony or omnibridge/laravel yet:
build the `Registry` once where the framework builds its services - [Symfony](symfony.md),
[Laravel](laravel.md).
