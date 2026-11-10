<?php

namespace Omnidns\Http;

use Omnidns\Exception\ProviderException;
use Omnidns\Exception\UnreachableException;
use Omnisense\Contracts\Http\NoRetry;
use Omnisense\Contracts\Policy\Backoff;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A provider's JSON calls over the application's HTTP client:
 * omnisense/contracts' JsonClient with omnidns's exceptions - a 404 read
 * as "not known" (null), any other error status a ProviderException naming
 * the path only (its code the status), a network failure an
 * UnreachableException, the request's secrets kept out of both.
 *
 * Given the provider's Backoff (Config::backoff() in a factory's build()),
 * it waits while the provider asked to and reads every response's
 * Retry-After and RateLimit fields: a 429 is a UsageLimitReachedException
 * with the wait asked. Json::request() makes its calls through it.
 *
 * The client given is kept with its retries off (omnisense/contracts'
 * Http\NoRetry): Symfony's RetryableHttpClient (framework.http_client's
 * retry_failed) would send a write again on a 429, 502 or 503, and a 429
 * would never reach the Backoff. A MockHttpClient is kept as given.
 */
final class JsonClient extends \Omnisense\Contracts\Http\JsonClient
{
    /** @param array<string, string|list<string>> $headers sent with every request */
    public function __construct(HttpClientInterface $http, string $provider, array $headers = [], ?Backoff $backoff = null)
    {
        // Sent once: the application's client keeps no retry of its own (omnisense/contracts' Http\NoRetry, from
        // contracts 1568f2c; a site whose vendor holds an older kernel keeps its client as given).
        parent::__construct(class_exists(NoRetry::class) ? NoRetry::of($http) : $http, $provider, $headers, $backoff);
    }

    protected function answered(string $what, int $status, string $detail): \Throwable
    {
        return 200 === $status
            ? ProviderException::answered($this->provider, $what, 'something that is not JSON')
            : ProviderException::answered($this->provider, $what, $status, $status);
    }

    protected function unreachable(string $message, \Throwable $previous): \Throwable
    {
        return new UnreachableException($message, 0, $previous);
    }
}
