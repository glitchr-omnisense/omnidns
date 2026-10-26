---
title: Symfony
order: 15
---

# Symfony

There is no Omnidns bundle, and omnibridge/symfony has no Omnidns wiring yet: a Symfony
application declares the registry as a service of its own. Gandi's and Scaleway's factories take
the application's `http_client` by autowiring (`Symfony\Contracts\HttpClient\HttpClientInterface`);
OVH's and Cloudflare's take their own client, optional.

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true

    Omnidns\Gandi\GandiFactory: ~
    Omnidns\Scaleway\ScalewayFactory: ~
    Omnidns\Ovh\OvhFactory: ~                 # no argument: ovh/ovh makes its own Guzzle client
    Omnidns\Cloudflare\CloudflareFactory: ~   # no argument: cloudflare/sdk's Guzzle adapter

    Omnidns\Registry:
        arguments:
            $factories:
                - '@Omnidns\Gandi\GandiFactory'
                - '@Omnidns\Ovh\OvhFactory'
                - '@Omnidns\Cloudflare\CloudflareFactory'
            $config:
                registrar:
                    factory: ovh
                    options:
                        application_key: '%env(OVH_APPLICATION_KEY)%'
                        application_secret: '%env(OVH_APPLICATION_SECRET)%'
                        consumer_key: '%env(OVH_CONSUMER_KEY)%'
                dns:
                    factory: cloudflare
                    options: { token: '%env(CLOUDFLARE_API_TOKEN)%' }
```

```sh
# .env.local, or bin/console secrets:set
OVH_APPLICATION_KEY=...
OVH_APPLICATION_SECRET=...
OVH_CONSUMER_KEY=...
CLOUDFLARE_API_TOKEN=...
```

`OvhFactory` and `CloudflareFactory` have an optional constructor argument whose type
(`GuzzleHttp\Client`, `Cloudflare\API\Adapter\Adapter`) is not a service the application has;
if autowiring complains, give them `arguments: [~]`, or wire a client explicitly.

Then inject the registry:

```php
use Omnidns\Model\Record;
use Omnidns\Registry;

final class SubdomainProvisioner
{
    public function __construct(private readonly Registry $domains)
    {
    }

    public function open(string $client): void
    {
        $this->domains->dns('dns')->add('example.org', new Record('CNAME', $client, 'apps.example.org', options: ['proxied' => true]));
    }
}
```

Nothing is built when the container compiles: a provider is built, and its required options
checked, the first time the registry is asked for it. Orders cost money: keep `register()` and
`renew()` behind an explicit action of a person, not in a message handler that retries.

This page was written from the classes' constructors; it was not run in a Symfony kernel.
