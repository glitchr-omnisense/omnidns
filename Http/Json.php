<?php

namespace Omnidns\Http;

use Omnidns\Exception\ProviderException;
use Omnidns\Exception\UnreachableException;
use Omnisense\Contracts\Policy\Backoff;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A JSON request on the application's HTTP client, its failures turned into
 * omnidns's exceptions (an empty answer is an empty array), through
 * omnidns's JsonClient and the provider's Backoff when it is given; and
 * typed reads of what came back, for providers that walk an answer they do
 * not control.
 */
final class Json
{
    /**
     * The call goes through omnidns's JsonClient (omnisense/contracts'):
     * given the provider's Backoff, it waits while the provider asked to,
     * and a 429 is a UsageLimitReachedException holding the next calls for
     * its Retry-After; without one, a 429 is a ProviderException as any
     * other error status. Every error status is given back by raw(), so
     * that the provider's own words make the message.
     *
     * @param array<string, mixed> $options the client's options (headers, json, query...)
     *
     * @return array<mixed> the decoded answer
     */
    public static function request(HttpClientInterface $http, string $method, string $url, array $options, string $provider, string $what, ?Backoff $backoff = null): array
    {
        $headers = [];
        foreach (\is_array($options['headers'] ?? null) ? $options['headers'] : [] as $name => $value) {
            if (\is_string($name) && (\is_string($value) || \is_array($value))) {
                $headers[$name] = \is_string($value) ? $value : array_values(array_map(static fn ($one) => \is_scalar($one) ? (string) $one : '', $value));
            }
        }
        unset($options['headers']);
        try {
            $answer = (new JsonClient($http, $provider, [], $backoff))->raw($method, $url, $options, $headers, range(400, 599));
        } catch (UnreachableException $e) {
            // The client's message, its secrets masked (the previous exception's), under what was asked rather than the path.
            $previous = $e->getPrevious() ?? $e;

            throw Redaction::withoutArguments(new UnreachableException(\sprintf('%s could not be reached for %s: %s', $provider, $what, $previous->getMessage()), 0, $previous));
        }
        $status = $answer->status;
        $body = $answer->body;
        $data = '' === trim($body) ? [] : json_decode($body, true);
        if ($status >= 300) {
            // The provider's own words when it gives them: Gandi's and Scaleway's "message", Cloudflare's errors.
            $message = self::string($data, 'message') ?? self::string($data, 'errors', 0, 'message') ?? self::string($data, 'error');

            throw Redaction::withoutArguments(ProviderException::answered($provider, $what, $status.(null === $message ? '' : " ($message)"), $status));
        }

        return \is_array($data) ? $data : throw Redaction::withoutArguments(ProviderException::answered($provider, $what, 'something that is not JSON'));
    }

    /** The value at a path of keys; null when any step is missing. */
    public static function get(mixed $data, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }

        return $data;
    }

    /** A non-empty string at the path, or null. */
    public static function string(mixed $data, string|int ...$path): ?string
    {
        $value = self::get($data, ...$path);

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }

    public static function int(mixed $data, string|int ...$path): ?int
    {
        $value = self::get($data, ...$path);

        return is_numeric($value) ? (int) $value : null;
    }

    public static function bool(mixed $data, string|int ...$path): bool
    {
        return true === self::get($data, ...$path);
    }

    /** @return list<mixed> the list at the path; empty when there is none */
    public static function list(mixed $data, string|int ...$path): array
    {
        $value = self::get($data, ...$path);

        return \is_array($value) ? array_values($value) : [];
    }

    /** A date at the path, or null. */
    public static function date(mixed $data, string|int ...$path): ?\DateTimeImmutable
    {
        $value = self::string($data, ...$path);
        try {
            return null === $value ? null : new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
