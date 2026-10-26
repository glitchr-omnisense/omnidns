# Installation, in plain PHP

    composer require omnisense/omnidns omnidns/gandi symfony/http-client

Each provider is its own package: `omnidns/ovh`, `omnidns/gandi`, `omnidns/cloudflare`,
`omnidns/scaleway`. The block below runs as written in the tests (`Tests/DocsTest.php`), on the
in-memory provider omnidns ships for tests and demos; in production its factory is a provider's
(`new GandiFactory($httpClient)` with `['token' => ...]`), the calls the same.

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
