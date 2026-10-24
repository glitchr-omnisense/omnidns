<?php

namespace Omnidns\Exception;

/** The provider answered, and not with what was asked: an error status, a domain not in the account, an order refused. */
final class ProviderException extends \RuntimeException implements OmnidnsException
{
    /** @param int $code the HTTP status when the provider answered with one, as the exception's code */
    public static function answered(string $provider, string $what, int|string $status, int $code = 0): self
    {
        return new self(\sprintf('%s answered %s for %s.', $provider, (string) $status, $what), $code);
    }
}
