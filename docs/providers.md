# Writing a provider

A provider is a package `omnidns/<name>` with a factory and a provider class:

- the factory extends `Omnidns\ProviderFactory`: `populate()` names it (`omnidns.factory_name`),
  lists its required options (`omnidns.required_options`) and the defaults of the others;
  `build()` returns the provider. The HTTP client (or the official client's adapter) comes
  through the factory's constructor, never created inside unless none is given.
- before writing a client, look for a maintained framework-free one and wrap it (OVH: ovh/ovh;
  Cloudflare: cloudflare/sdk); say in the README what was found.
- the provider implements the contracts it can answer - `AvailabilityInterface`,
  `RegistrarInterface`, `DomainsInterface`, `NameserversInterface`, `DnsInterface` - and says so
  in `capabilities()`, with the record types its DNS takes and the terms that apply.
- names are normalized with `DomainName::normalize()`; records hold a name relative to the zone
  (`Record::relative()` for a provider that gives full names), the MX priority apart
  (`Record::fromValue()` / `value()` for a provider that writes it in the value); a record type
  outside `Record::TYPES` is left out of `records()`.
- `update()` and `delete()` take a record with or without an id: without one, find it with
  `Record::findIn()` among `records($zone, $type, $name)`.
- every call through `Http\Json::request()` (or the wrapped client's errors turned into
  `ProviderException` / `UnreachableException`).
- tests run in the core's harness (`docker/harness/plugins.php` lists the providers), on
  recorded answers with each request checked, with a `BareTest`; a `LiveTest` (group `live`)
  only where the provider can be read without an account.
