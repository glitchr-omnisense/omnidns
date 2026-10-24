<?php

namespace Omnidns\Model;

use Omnidns\Exception\InvalidArgumentException;

/**
 * One DNS record of a zone: its type, its name relative to the zone ("" for
 * the apex, "www", "_dmarc"), its content (an address, a host, a text), its
 * TTL (null: the zone's default) and, for MX, its priority apart. A provider
 * that names records gives its id; one that groups them by name and type
 * (Gandi's rrsets) gives none, and a record is then known by its name, type
 * and content. Options are what one provider keeps besides (Cloudflare's
 * "proxied"), handed back to it on an update.
 */
final readonly class Record
{
    public const TYPES = ['A', 'AAAA', 'CAA', 'CNAME', 'MX', 'NS', 'SRV', 'TXT'];

    public string $type;
    public string $name;

    /** @param array<string, mixed> $options */
    public function __construct(
        string $type,
        string $name,
        public string $content,
        public ?int $ttl = null,
        public ?int $priority = null,
        public ?string $id = null,
        public array $options = [],
    ) {
        $type = strtoupper($type);
        if (!\in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a record type omnidns handles (%s).', $type, implode(', ', self::TYPES)));
        }
        $this->type = $type;
        $this->name = self::normalizeName($name);
    }

    /** A name relative to its zone as records hold it: lower case, no trailing dot, "" for the apex ("@"). */
    public static function normalizeName(string $name): string
    {
        $name = strtolower(rtrim(trim($name), '.'));

        return '@' === $name ? '' : $name;
    }

    /**
     * A record from a provider that writes an MX's priority in its value ("10 mx.example.org.").
     *
     * @param array<string, mixed> $options
     */
    public static function fromValue(string $type, string $name, string $value, ?int $ttl = null, ?string $id = null, array $options = []): self
    {
        if ('MX' === strtoupper($type) && preg_match('~^(\d+)\s+(\S+)$~', trim($value), $m)) {
            return new self($type, $name, $m[2], $ttl, (int) $m[1], $id, $options);
        }

        return new self($type, $name, $value, $ttl, null, $id, $options);
    }

    /** The value as a zone file writes it: an MX's priority before its host. */
    public function value(): string
    {
        return 'MX' === $this->type && null !== $this->priority ? $this->priority.' '.$this->content : $this->content;
    }

    /** The record's full name in a zone: "www.example.org", "example.org" for the apex. */
    public function fqdn(string $zone): string
    {
        $zone = DomainName::normalize($zone);

        return '' === $this->name ? $zone : $this->name.'.'.$zone;
    }

    /** A name a provider gives in full ("www.example.org"), relative to the zone ("www"). */
    public static function relative(string $name, string $zone): string
    {
        $name = strtolower(rtrim($name, '.'));
        $zone = DomainName::normalize($zone);
        if ($name === $zone) {
            return '';
        }

        return str_ends_with($name, '.'.$zone) ? substr($name, 0, -\strlen($zone) - 1) : $name;
    }

    /** The same record: the same id when both have one; else the same name, type and content (a host's trailing dot and a text's quotes aside). */
    public function sameAs(self $other): bool
    {
        if (null !== $this->id && null !== $other->id) {
            return $this->id === $other->id;
        }

        return $this->type === $other->type && $this->name === $other->name && self::canonical($this->type, $this->content) === self::canonical($other->type, $other->content) && ('MX' !== $this->type || null === $this->priority || null === $other->priority || $this->priority === $other->priority);
    }

    /**
     * The one of these records that is the same as this one.
     *
     * @param iterable<self> $records
     */
    public function findIn(iterable $records): ?self
    {
        foreach ($records as $record) {
            if ($this->sameAs($record)) {
                return $record;
            }
        }

        return null;
    }

    public function withId(?string $id): self
    {
        return new self($this->type, $this->name, $this->content, $this->ttl, $this->priority, $id, $this->options);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['type' => $this->type, 'name' => $this->name, 'content' => $this->content, 'ttl' => $this->ttl, 'priority' => $this->priority, 'id' => $this->id, 'options' => $this->options];
    }

    private static function canonical(string $type, string $content): string
    {
        $content = trim($content, ' "');

        // A host's case and trailing dot do not matter; a text's case does.
        return \in_array($type, ['TXT', 'CAA'], true) ? $content : strtolower(rtrim($content, '.'));
    }
}
