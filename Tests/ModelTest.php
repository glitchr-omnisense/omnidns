<?php

namespace Omnidns\Tests;

use Omnidns\Exception\InvalidArgumentException;
use Omnidns\Http\Json;
use Omnidns\Model\DomainInfo;
use Omnidns\Model\DomainName;
use Omnidns\Model\Money;
use Omnidns\Model\Record;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testADomainNameNormalized(): void
    {
        self::assertSame('example.org', DomainName::normalize(' Example.ORG. '));
        self::assertSame('xn--bcher-kva.example', DomainName::normalize('xn--bcher-kva.example'));
        self::assertSame('_dmarc.example.org', DomainName::normalize('_dmarc.example.org'));
    }

    public function testNotADomainName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DomainName::normalize('localhost');
    }

    public function testARecordsNameIsRelativeToItsZone(): void
    {
        self::assertSame('', (new Record('a', '@', '192.0.2.1'))->name);
        self::assertSame('www', (new Record('CNAME', 'WWW.', 'example.org.'))->name);
        self::assertSame('A', (new Record('a', '', '192.0.2.1'))->type);
        self::assertSame('example.org', (new Record('A', '', '192.0.2.1'))->fqdn('example.org'));
        self::assertSame('www.example.org', (new Record('A', 'www', '192.0.2.1'))->fqdn('Example.org.'));
        self::assertSame('www', Record::relative('www.example.org', 'example.org'));
        self::assertSame('', Record::relative('example.org.', 'example.org'));
        self::assertSame('a.b', Record::relative('a.b.example.org', 'example.org'));
    }

    public function testAnUnknownType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Record('SPF', '', 'v=spf1 -all');
    }

    public function testAnMxPriorityApartAndBack(): void
    {
        $mx = Record::fromValue('MX', '', '10 mx.example.org.');
        self::assertSame(['mx.example.org.', 10, '10 mx.example.org.'], [$mx->content, $mx->priority, $mx->value()]);
        self::assertSame('10 not-an-mx', Record::fromValue('TXT', '', '10 not-an-mx')->content);
    }

    public function testTheSameRecord(): void
    {
        $a = new Record('A', 'www', '192.0.2.1', id: '1');
        self::assertTrue($a->sameAs(new Record('A', 'www', '192.0.2.9', id: '1')), 'the same id');
        self::assertFalse($a->sameAs(new Record('A', 'www', '192.0.2.1', id: '2')), 'another id');
        self::assertTrue($a->sameAs(new Record('A', 'WWW', '192.0.2.1')), 'no id: name, type, content');
        self::assertTrue((new Record('CNAME', 'w', 'Example.org.'))->sameAs(new Record('CNAME', 'w', 'example.org')), 'a host\'s case and dot aside');
        self::assertFalse((new Record('TXT', 't', 'ABC'))->sameAs(new Record('TXT', 't', 'abc')), 'a text\'s case counts');
        self::assertTrue((new Record('TXT', 't', '"abc"'))->sameAs(new Record('TXT', 't', 'abc')), 'its quotes do not');
        self::assertFalse((new Record('MX', '', 'mx.example.org', priority: 10))->sameAs(new Record('MX', '', 'mx.example.org', priority: 20)));
        self::assertSame('2', (new Record('A', '', '192.0.2.2'))->findIn([new Record('A', '', '192.0.2.1', id: '1'), new Record('A', '', '192.0.2.2', id: '2')])?->id);
    }

    public function testDaysLeft(): void
    {
        $now = new \DateTimeImmutable('2026-10-24');
        self::assertSame(8, (new DomainInfo('example.org', new \DateTimeImmutable('2026-11-01')))->daysLeft($now));
        self::assertSame(-23, (new DomainInfo('example.org', new \DateTimeImmutable('2026-10-01')))->daysLeft($now));
        self::assertNull((new DomainInfo('example.org'))->daysLeft($now));
    }

    public function testMoneyAndReads(): void
    {
        self::assertSame('8.39', Money::fromDecimal(8.39, 'eur')->decimal());
        self::assertSame('16.78', Money::fromDecimal('8.39', 'EUR')->multiply(2)->decimal());
        self::assertSame(3, Json::int(['a' => ['b' => '3']], 'a', 'b'));
        self::assertSame([], Json::list(['a' => 1], 'a'));
    }
}
