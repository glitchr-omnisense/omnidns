# Changelog

## [Unreleased]

### Fixed

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
