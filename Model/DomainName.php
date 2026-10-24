<?php

namespace Omnidns\Model;

use Omnidns\Exception\InvalidArgumentException;

/** A domain name as every provider is asked it: lower case, no trailing dot, labels a registry accepts. */
final class DomainName
{
    public static function normalize(string $name): string
    {
        $name = strtolower(rtrim(trim($name), '.'));
        if (\strlen($name) > 253 || !preg_match('~^(?:[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$~', $name)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a domain name (an internationalized one goes in its xn-- form).', $name));
        }

        return $name;
    }
}
