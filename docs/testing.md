---
title: Testing
order: 60
---

# Testing

## The in-memory provider

`Omnidns\Testing\MemoryProvider` is a registrar and a DNS host in memory, every contract
answered: for tests, demos and an application's fixtures. Its factory, `MemoryFactory` (name
`memory`), takes the domains held, those held by someone else, and a year's price
([options](configuration.md#memory)):

```php
use Omnidns\Model\Money;
use Omnidns\Model\Record;
use Omnidns\Registry;
use Omnidns\Testing\MemoryFactory;

$registry = new Registry([new MemoryFactory()], [
    'registrar' => ['factory' => 'memory', 'options' => [
        'domains' => ['example.org' => '2027-03-01'],
        'taken' => ['example.com'],
        'price' => Money::fromDecimal('8.39', 'EUR'),
    ]],
]);

$registry->availability('registrar')->check('example.com')->reason;      // "registered"
$registry->registrar('registrar')->renew('example.org', 2);               // Order: renew, done, 16.78 EUR
$registry->domains('registrar')->domain('example.org')->expiresAt;        // 2029-03-01

$dns = $registry->dns('registrar');
$dns->add('example.org', new Record('A', 'www', '192.0.2.10'));
$dns->update('example.org', new Record('A', 'www', '192.0.2.10'), new Record('A', 'www', '192.0.2.11', 300));
```

How it behaves:

- `check()`: free unless held or taken; priced (first year and renewal) with `price` when free;
  `reason` `registered` otherwise.
- `register()`: a domain not free is a `ProviderException`; a free one is held from 2026-01-01
  (the provider's fixed "now") for the years asked, status `ok`, auto-renewing, served by
  `ns1.memory.test` and `ns2.memory.test`, its zone empty; the order `done`, its id
  `order-<n>`, its price `price` times the years.
- `renew()`: the end moved by the years; a domain not held is a `ProviderException`.
- `setNameservers()`: kept, lower case, no trailing dot.
- DNS: a zone exists for each domain held (else `ProviderException` `not served here`); records
  get ids `1`, `2`... (one counter shared with the orders); `update()` and `delete()` find the
  record with `sameAs()`.

The application keeps the name it uses (`registrar`); only the factory changes.

## A provider on recorded answers

Gandi's and Scaleway's factories take a `MockHttpClient`:

```php
use Omnidns\Exception\ProviderException;
use Omnidns\Gandi\GandiFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$gandi = (new GandiFactory(new MockHttpClient(new MockResponse(
    '{"code":404,"message":"The resource could not be found.","object":"HTTPNotFound","cause":"Not Found"}',
    ['http_code' => 404],
))))->create(['token' => 'test']);

try {
    $gandi->domain('example.org');
} catch (ProviderException $e) {
    $e->getCode();   // 404
}
```

OVH's factory takes a `GuzzleHttp\Client` - one built on a Guzzle `MockHandler` answers as
recorded - and Cloudflare's a `Cloudflare\API\Adapter\Adapter`: its tests use a recording adapter
of their own (`Tests/RecordingAdapter.php`), which an application may copy. The providers' tests
route the answers by method and path (`Tests/Router.php` in Gandi and Scaleway).

## Live checks

None: every provider needs an account and a key, OVH's availability included. A first use against
a real account is the check still owed ([Verified, and not](verified.md)); Gandi's sandbox
(`endpoint: https://api.sandbox.gandi.net/v5`) and `dry_run` are the safe places to start.
