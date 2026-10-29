---
title: Errors
order: 50
---

# Errors

Every exception Omnidns throws implements `Omnidns\Exception\OmnidnsException` (a `\Throwable`):

| Exception | Extends | When |
|---|---|---|
| `InvalidConfigException` | `\InvalidArgumentException` | a provider not configured, a factory not installed, a required option missing (`The "scaleway" provider needs: secret_key.`), an option a call needs (`The "scaleway" provider needs a project_id to register a domain.`, Cloudflare's `account_id`), a `Money` with a bad currency or amount |
| `InvalidArgumentException` | `\InvalidArgumentException` | a value no provider takes, before any call: a malformed domain name, a record type outside `Record::TYPES`, a duration outside 1-10 years, an owner a provider cannot take (none at Scaleway, not in full at Gandi, not a handle at OVH) |
| `NotSupportedException` | `\LogicException` | the registry's typed getter asked of a provider without that contract, before any call |
| `ProviderException` | `\RuntimeException` | the provider answered, and not with what was asked: an error status, a domain not in the account, a record not found, an order refused, a body that is not JSON |
| `UnreachableException` | `\RuntimeException` | the provider could not be reached: a network error, a timeout; a copy of the client's exception is the previous one ([no secret in it](#no-secret-in-a-failure)) |

Messages, as the providers write them (run on 2026-10-26 in the [harness](harness.md)):

```
"exa mple.org" is not a domain name (an internationalized one goes in its xn-- form).
"SPF" is not a record type omnidns handles (A, AAAA, CAA, CNAME, MX, NS, SRV, TXT).
A domain is registered or renewed for 1 to 10 years, not 11.
Gandi needs the owner in full to register a domain (Contact: names, e-mail, phone, address, country, type).
Gandi answered 404 (The resource could not be found.) for the domain example.org.
Gandi could not be reached for the availability of example.org: Could not resolve host
memory answered no such record for a record of example.org.
```

## No secret in a failure

An `UnreachableException` keeps the request's secrets out. The HTTP client's message quotes the URL
called: its key parameters, a password in its user part and every secret of the request's options
(Gandi's and Cloudflare's bearer token, Scaleway's `X-Auth-Token`, OVH's application and consumer
keys and signature) are masked (`api_key=***`, `Http\Redaction::text()`). The previous exception is
a copy of the client's (`Redaction::copy()`: the same class and code, that message, without the
request a Guzzle exception carried), and no trace of the chain keeps its arguments, where the
query and headers stood: a log that writes the whole chain, arguments included
(`zend.exception_ignore_args` off), holds no key.

## ProviderException's code

Gandi and Scaleway (through `Http\Json`) and Cloudflare (when Guzzle has the response) give the
HTTP status as the exception's code: `$e->getCode()` is 404 for a domain not in the account, 401
for a key refused, 429 for a rate limit. OVH's carries the status in its message only (code 0).
Each message keeps the provider's own words in brackets when it gave them: Gandi's and
Scaleway's `message`, Cloudflare's `errors[0].message`, OVH's `message`.

## What to catch where

| Where | Catch |
|---|---|
| a domain search | `InvalidArgumentException`: the name typed is not a domain; `ProviderException`, `UnreachableException`: "not available now" |
| an order | the same; a `ProviderException` after `register()` may mean the order was made and the answer lost - check `domains()` before trying again |
| a record change | `ProviderException` with "no such record": the zone changed since it was read |
| building the registry, a deploy | `InvalidConfigException`: a configuration error, to fix |
| a list over every provider | check the contract first (`instanceof`, `capabilities()`); `NotSupportedException` is a programming error |
