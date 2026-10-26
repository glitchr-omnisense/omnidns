---
title: Laravel
order: 16
---

# Laravel

omnibridge/laravel has no Omnidns provider: a service provider of the application builds the
registry once, from a config file. Gandi and Scaleway take a `symfony/http-client` client; OVH and
Cloudflare send with Guzzle through their own clients (both installed with their packages).

```php
// config/omnidns.php
return [
    'providers' => [
        'registrar' => ['factory' => 'gandi', 'options' => ['token' => env('GANDI_TOKEN')]],
        'dns' => ['factory' => 'cloudflare', 'options' => ['token' => env('CLOUDFLARE_API_TOKEN'), 'account_id' => env('CLOUDFLARE_ACCOUNT_ID')]],
    ],
];
```

```php
// app/Providers/OmnidnsServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Omnidns\Cloudflare\CloudflareFactory;
use Omnidns\Gandi\GandiFactory;
use Omnidns\Registry;
use Symfony\Component\HttpClient\HttpClient;

final class OmnidnsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class, fn (): Registry => new Registry(
            [new GandiFactory(HttpClient::create(['timeout' => 20])), new CloudflareFactory()],
            config('omnidns.providers'),
        ));
    }
}
```

Register the provider (`bootstrap/providers.php` on Laravel 11 and later), then:

```php
use Omnidns\Registry;

$days = app(Registry::class)->domains('registrar')->domain('example.org')->daysLeft();
```

An unset `env()` is `null`, which a required option reports as missing when the provider is
first asked for. Configuration caching (`php artisan config:cache`) keeps working: the options are
plain arrays. This page was written from the registry's API; it was not run in a Laravel
application.
