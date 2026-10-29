# Changelog

## [Unreleased]

### Fixed

- The registry's typed getters (`availability()`, `registrar()`, `domains()`, `nameservers()`,
  `dns()`) check the provider's `capabilities()` as well as its interface: Cloudflare without
  `account_id` implements `DomainsInterface` and was given by `domains()`, whose call then threw
  `InvalidConfigException`; the getter now throws the `NotSupportedException`.
