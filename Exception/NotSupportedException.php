<?php

namespace Omnidns\Exception;

/** A provider asked for what it cannot give: a registration asked of a provider that only hosts DNS. */
final class NotSupportedException extends \LogicException implements OmnidnsException
{
    public static function capability(object $provider, string $name, string $capability): self
    {
        return new self(\sprintf('The "%s" DNS provider (%s) does not %s.', $name, $provider::class, $capability));
    }
}
