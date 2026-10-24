<?php

namespace Omnidns\Tests;

use Omnidns\Capabilities;
use Omnidns\Config;
use Omnidns\DnsInterface;
use Omnidns\Exception\InvalidConfigException;
use Omnidns\Exception\NotSupportedException;
use Omnidns\Exception\ProviderException;
use Omnidns\Model\Record;
use Omnidns\ProviderFactory;
use Omnidns\ProviderInterface;
use Omnidns\Registry;
use Omnidns\Testing\MemoryFactory;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testProvidersByNameBuiltOnce(): void
    {
        $registry = new Registry([new MemoryFactory()], ['m' => ['factory' => 'memory', 'options' => ['domains' => ['example.org' => '2027-01-01']]]]);

        self::assertSame($registry->get('m'), $registry->get('m'));
        self::assertSame(['example.org'], $registry->domains('m')->domains());
        self::assertSame(['availability', 'registrar', 'domains', 'nameservers', 'dns'], $registry->get('m')->capabilities()->contracts());
        self::assertSame(['m'], $registry->names());
        self::assertSame(['memory'], $registry->factories());
    }

    public function testADnsHostIsNoRegistrar(): void
    {
        $registry = new Registry([new DnsOnlyFactory()], ['dns' => ['factory' => 'dns-only']]);

        self::assertSame([], $registry->dns('dns')->records('example.org'));
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('The "dns" DNS provider (Omnidns\Tests\DnsOnly) does not register or renew domains.');
        $registry->registrar('dns');
    }

    public function testAProviderNotConfigured(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('No "x" DNS provider; configured: none.');
        (new Registry([], []))->get('x');
    }

    public function testTheMemoryProviderRefusesWhatIsNotFree(): void
    {
        $registry = new Registry([new MemoryFactory()], ['m' => ['factory' => 'memory', 'options' => ['taken' => ['example.org']]]]);

        self::assertSame('registered', $registry->availability('m')->check('example.org')->reason);
        $this->expectException(ProviderException::class);
        $registry->registrar('m')->register('example.org');
    }

    public function testTheMemoryProviderRefusesARecordItDoesNotHold(): void
    {
        $registry = new Registry([new MemoryFactory()], ['m' => ['factory' => 'memory', 'options' => ['domains' => ['example.org' => '2027-01-01']]]]);

        $this->expectException(ProviderException::class);
        $registry->dns('m')->delete('example.org', new Record('A', '', '192.0.2.1'));
    }
}

final class DnsOnly implements DnsInterface
{
    public function getName(): string
    {
        return 'dns-only';
    }

    public function getTitle(): string
    {
        return 'DNS only';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(dns: true);
    }

    public function records(string $zone, ?string $type = null, ?string $name = null): array
    {
        return [];
    }

    public function add(string $zone, Record $record): Record
    {
        return $record;
    }

    public function update(string $zone, Record $current, Record $new): Record
    {
        return $new;
    }

    public function delete(string $zone, Record $record): void
    {
    }
}

final class DnsOnlyFactory extends ProviderFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults(['omnidns.factory_name' => 'dns-only']);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new DnsOnly();
    }
}
