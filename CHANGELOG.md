# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable version. It requires `omnisense/contracts` `^1.0` with no stability flag, so `composer require omnisense/omnidns` installs it in a project with Composer's default minimum-stability; the 1.x series follows semantic versioning. A tag `vX.Y.Z` is released by `.github/workflows/release.yml` once the package's CI passes on it.

### Changed

- `Http\JsonClient` always takes the client through `NoRetry::of()`: the fallback for a kernel older
  than omnisense/contracts 1568f2c (the client kept as given when `NoRetry` was missing) is gone,
  `^1.0` always has it.
- The README opens on the install line and a first call that needs no account (Gandi answered by
  the recorded client of omnisense/contracts' Testing kit), and says what each provider needs.

### Added

- 2026-11-10, writes sent once: `Http\JsonClient`, which `Http\Json::request()` sends every
  Gandi and Scaleway call through, keeps the HTTP client it is given with its retries off
  (omnisense/contracts' `Http\NoRetry`, which omnisense/contracts 1568f2c brings; with an older
  kernel the client is kept as given), so Symfony's `RetryableHttpClient` never sends a DNS
  record created, changed or deleted twice. A `MockHttpClient` is kept as given. Cloudflare (its
  SDK's adapter) and OVH (Guzzle) do not send through a Symfony client. docs/policies.md.
  Tests/NoRetryTest.

### Changed

- omnisense/omnidns builds on omnisense/contracts, the kernel every omnisense family shares.
  `Config`, `ProviderInterface`, `ProviderFactoryInterface`, `ProviderFactory` (`populate()` and
  `build()` as before), `Registry` and `Http\Redaction` keep their names and API, each a thin
  subclass of the kernel's (prefix `omnidns`, omnidns's exceptions). `OmnidnsException` extends
  the kernel's `ExceptionInterface`.
- `Http\Json::request()` makes its call through the new `Http\JsonClient` (the kernel's, with
  omnidns's exceptions) and takes the provider's `Backoff` as an optional last argument. Its
  messages, codes and errors are unchanged; the static readers stay.
- The registry's typed getters give the provider behind its guard (`Policy\GuardedAvailability`,
  `GuardedRegistrar`, `GuardedDomains`, `GuardedNameservers`, `GuardedDns`; `inner()` gives the
  provider): a call over a rate limit of its usage policy is never made, a 429 and its
  Retry-After hold every call of the provider (`Exception\UsageLimitReachedException`). `get()`
  stays unguarded.

### Added

- `Capabilities::policy()`: the provider's `UsagePolicy`, given (`policy:`) or made of `$terms`,
  which stays, deprecated.
- A site's `policy` (a tightening, never looser) and `usage` per provider; `Registry::policy()`,
  `policies()`, `guard()`, `names()` by contract; a shared `RateLimiterInterface` as the
  registry's third argument. `docs/policies.md`.
- The harness installs omnisense/contracts from its checkout (`OMNISENSE_CONTRACTS`) or GitHub,
  runs its tests, and names its entrypoint.

### Fixed

- No exception `Json::request()` throws keeps its trace's arguments: an error answered (a refused key,
  nothing there, a server error, a body that is not JSON) kept the request's options - the query,
  headers and token with the key - in its trace (`zend.exception_ignore_args` off), as the
  `UnreachableException` did before its fix.
- An `UnreachableException` no longer carries the request's secrets. `Http\Json::request()` put the
  HTTP client's message, the whole URL, in its message, wrapped the client's own exception as its
  previous, and every trace of the chain kept the arguments of the calls under it (the query and
  headers with the key, when `zend.exception_ignore_args` is off). The message and the previous -
  now a copy of the client's exception, the same class and code - have the key's parameters, the
  URL's user part and the request's secrets masked as `***` (`Http\Redaction`), and no trace of
  the chain keeps its arguments.
- The registry's typed getters (`availability()`, `registrar()`, `domains()`, `nameservers()`,
  `dns()`) check the provider's `capabilities()` as well as its interface: Cloudflare without
  `account_id` implements `DomainsInterface` and was given by `domains()`, whose call then threw
  `InvalidConfigException`; the getter now throws the `NotSupportedException`.
