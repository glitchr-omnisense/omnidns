<?php

namespace Omnidns\Tests;

use Omnidns\Capabilities;
use Omnidns\Exception\OmnidnsException;
use Omnidns\Exception\ProviderException;
use Omnidns\Exception\UsageLimitReachedException;
use Omnidns\Http\Json;
use Omnidns\Model\Record;
use Omnidns\Policy\GuardedDns;
use Omnidns\Registry;
use Omnidns\Testing\MemoryFactory;
use Omnidns\Testing\MemoryProvider;
use Omnisense\Contracts\Policy\Backoff;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\Permission;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Usage policies through omnidns's registry: a site's limit counted per
 * operation with the call over it never made, a 429 and its Retry-After
 * holding the next calls (Json::request() given the provider's Backoff), a
 * tightening accepted, a looser one refused, the older Capabilities field
 * made into a policy.
 */
final class PolicyTest extends TestCase
{
    public function testASitesLimitIsCountedPerOperationAndTheCallOverItIsNeverMade(): void
    {
        $registry = new Registry([new MemoryFactory()], ['m' => ['factory' => 'memory', 'options' => ['domains' => ['example.org' => '2027-03-01']], 'policy' => ['rateLimits' => [['max' => 2, 'window' => 'minute', 'operations' => ['add']]]]]]);
        $dns = $registry->dns('m');

        $dns->add('example.org', Record::fromValue('A', 'a', '192.0.2.1'));
        $dns->add('example.org', Record::fromValue('A', 'b', '192.0.2.2'));
        try {
            $dns->add('example.org', Record::fromValue('A', 'c', '192.0.2.3'));
            self::fail('The call over the site\'s limit was made.');
        } catch (UsageLimitReachedException $e) {
            self::assertInstanceOf(OmnidnsException::class, $e, 'the family\'s catch holds it');
            self::assertSame('2/minute/application/add', $e->limit?->id());
            self::assertGreaterThan(0, $e->retryAfter);
        }
        $provider = $registry->get('m');
        self::assertInstanceOf(MemoryProvider::class, $provider);
        self::assertCount(2, $provider->records('example.org'), 'the refused call reached nothing');
        self::assertCount(2, $dns->records('example.org'), 'another operation is not counted under that limit');
        self::assertInstanceOf(GuardedDns::class, $dns);
        self::assertSame($registry->get('m'), $dns->inner());
        self::assertSame($dns, $registry->dns('m'), 'one guarded contract per provider');
    }

    public function testA429AndItsRetryAfterHoldTheNextCalls(): void
    {
        $made = 0;
        $http = new MockHttpClient(static function () use (&$made): MockResponse {
            ++$made;

            return new MockResponse('{"message":"too many requests"}', ['http_code' => 429, 'response_headers' => ['Retry-After: 90']]);
        });
        $backoff = new Backoff(new InMemoryRateLimiter(), 'example', UsageLimitReachedException::class);

        try {
            Json::request($http, 'GET', 'https://api.example/v1/zones', [], 'Example', 'the zones', $backoff);
            self::fail('A 429 was read as an answer.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(90, $e->retryAfter);
            self::assertSame('example answered 429 Too Many Requests: try again in 90 s.', $e->getMessage());
        }
        try {
            Json::request($http, 'GET', 'https://api.example/v1/zones', [], 'Example', 'the zones', $backoff);
            self::fail('A call went out while the provider asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(89, $e->retryAfter);
        }
        self::assertSame(1, $made, 'the wait held the next request back');
    }

    public function testWithoutABackoffA429IsAnErrorAnsweredAsBefore(): void
    {
        $http = new MockHttpClient(new MockResponse('{"message":"slow down"}', ['http_code' => 429, 'response_headers' => ['Retry-After: 90']]));

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Example answered 429 (slow down) for the zones.');
        Json::request($http, 'GET', 'https://api.example/v1/zones', [], 'Example', 'the zones');
    }

    public function testATighteningIsAcceptedAndALooserOneRefused(): void
    {
        $registry = new Registry([new MemoryFactory()], [
            'tight' => ['factory' => 'memory', 'policy' => ['rateLimits' => [['max' => 10, 'window' => 'hour']], 'rights' => ['commercial' => 'forbidden']]],
            'loose' => ['factory' => 'memory', 'policy' => ['rights' => ['commercial' => 'allowed']]],
        ]);

        $tight = $registry->policy('tight');
        self::assertSame(['10/hour/application/*'], array_map(static fn ($l) => $l->id(), $tight->rateLimits ?? []));
        self::assertSame(Permission::Forbidden, $tight->rights->commercial);
        self::assertSame(['tight', 'loose'], $registry->names('dns'));

        $this->expectException(InvalidPolicyException::class);
        $registry->dns('loose');
    }

    public function testTheOlderTermsFieldIsMadeIntoAPolicyAndReadFromOne(): void
    {
        $described = new Capabilities(dns: true, terms: ['https://example.org/terms', 'https://example.org/api']);
        self::assertNull($described->policy()->rateLimits, 'limits unknown');
        self::assertSame('https://example.org/terms', $described->policy()->provenance->terms);
        self::assertTrue($described->policy()->requirements->key);

        $declared = new Capabilities(dns: true, policy: (new MemoryProvider())->capabilities()->policy());
        self::assertSame([], $declared->policy()->rateLimits);
        self::assertSame([], $declared->terms, 'no address in the memory provider\'s policy');
        self::assertArrayHasKey('policy', $declared->toArray());
    }
}
