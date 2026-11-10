<?php

namespace Omnidns\Tests;

use Omnidns\Exception\ProviderException;
use Omnidns\Http\Json;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;

/**
 * The application's http_client with retry_failed on: Symfony's
 * RetryableHttpClient would create a record again on a 503. Json::request()
 * (Gandi's and Scaleway's calls) goes through omnidns's JsonClient, which
 * keeps the client's retries off: sent once, the 503 surfaced.
 */
final class NoRetryTest extends TestCase
{
    /** @var list<string> */
    private array $sent = [];

    /** @var list<MockResponse> */
    private array $answers = [];

    public function testARecordCreatedAnswered503IsSentOnceThroughARetryingClient(): void
    {
        $this->answers = [new MockResponse('{"message":"unavailable"}', ['http_code' => 503]), new MockResponse('{"id":"rec_1"}')];
        $retrying = new RetryableHttpClient(new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->sent[] = $method.' '.$url;

            return array_shift($this->answers) ?? new MockResponse('{}', ['http_code' => 500]);
        }), new GenericRetryStrategy(GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES, 0), 3);

        try {
            Json::request($retrying, 'POST', 'https://dns.example/v5/livedns/domains/example.org/records', ['json' => ['rrset_name' => 'www']], 'Gandi', 'a record');
            self::fail('a 503');
        } catch (ProviderException $e) {
            self::assertSame(503, $e->getCode());
        }
        self::assertSame(['POST https://dns.example/v5/livedns/domains/example.org/records'], $this->sent);
        self::assertCount(1, $this->answers, 'the second answer was never asked for');
    }

    public function testAMockHttpClientIsKeptAsGiven(): void
    {
        $mock = new MockHttpClient([new MockResponse('{"id":"rec_1"}')]);
        Json::request($mock, 'POST', 'https://dns.example/records', [], 'Gandi', 'a record');

        self::assertSame(1, $mock->getRequestsCount());
    }
}
