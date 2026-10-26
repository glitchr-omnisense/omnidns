<?php

namespace Omnidns\Tests;

use Omnidns\Registry;
use PHPUnit\Framework\TestCase;

/**
 * omnisense/omnidns with no framework: in PHP processes of their own
 * (this one has loaded PHPUnit), the harness's bare script builds a
 * registry, checks, registers and renews a domain, writes its zone - and every class of the
 * package is loaded. Nothing may come from outside the package but what it
 * requires: Symfony's HTTP client contracts (interfaces only) - no
 * framework, no HTTP client, no application, even in the harness where they
 * are installed beside it.
 */
final class BareTest extends TestCase
{
    private const FOREIGN = '~^(?:Symfony\\\\(?!Contracts\\\\HttpClient\\\\|Polyfill\\\\)|(?:Illuminate|Laravel|Doctrine|Twig|League|GuzzleHttp|Nyholm|Http|Omnibridge|Base|App)\\\\)~';
    private const ALLOWED_FILES = '~/vendor/(?:symfony/http-client-contracts|composer)/|/vendor/autoload\.php$~';

    public function testARegistryUsedByHandLoadsNothingButOmnidnsAndWhatItRequires(): void
    {
        $report = self::php([__DIR__.'/../docker/harness/bin/bare']);

        self::assertSame(['domain' => 'omnidns-bare.test', 'available' => true, 'price' => ['cents' => 839, 'currency' => 'EUR', 'decimal' => '8.39'], 'renewal' => ['cents' => 839, 'currency' => 'EUR', 'decimal' => '8.39'], 'premium' => false, 'reason' => null], $report['free'], 'the name normalized');
        self::assertFalse($report['taken']);
        self::assertSame(['register', 'done', '16.78', 2], [self::at($report, 'order', 'action'), self::at($report, 'order', 'status'), self::at($report, 'order', 'price', 'decimal'), self::at($report, 'order', 'years')]);
        self::assertSame(1, $report['renewal']);
        self::assertSame(['glitchr.test', 'omnidns-bare.test'], $report['domains']);
        self::assertSame('2029-01-01', $report['expires'], 'two years from 2026-01-01, and one more');
        self::assertSame(['ns1.example.net', 'ns2.example.net'], $report['nameservers']);
        self::assertSame([['A', 'omnidns-bare.test', '192.0.2.2', 300], ['MX', 'omnidns-bare.test', '10 mx.example.net.', null]], $report['records'], 'the A updated, the TXT deleted (found by its content, quotes aside)');
        self::assertSame(1, $report['mx']);
        self::assertSame('No "missing" factory for the "nowhere" DNS provider; installed: memory.', $report['missing']);

        self::assertContains('Omnidns\Registry', self::strings(self::at($report, 'loaded', 'symbols')));
        self::assertSame([], self::foreign(self::strings(self::at($report, 'loaded', 'symbols'))), 'no class of a framework, an HTTP client or an application');
        self::assertSame([], self::outside(self::strings(self::at($report, 'loaded', 'files')), self::strings(self::at($report, 'loaded', 'eager'))), 'no file but the package and what it requires');
    }

    public function testEveryClassLoadsWithTheRequiredPackagesAlone(): void
    {
        $report = self::php([__DIR__.'/Fixtures/load-all.php', self::autoload(), \dirname(__DIR__)]);

        self::assertGreaterThan(20, \count(self::strings($report['loaded'])));
        self::assertContains('Omnidns\Http\Json', self::strings($report['loaded']));
        self::assertSame([], self::foreign(self::strings($report['symbols'])));
        self::assertSame([], self::outside(self::strings($report['files']), self::strings($report['eager'])));
    }

    /** The check is not blind: an HTTP client's class, once loaded, is seen. */
    public function testTheReportSeesWhatIsLoaded(): void
    {
        if (!class_exists(\Symfony\Component\HttpClient\MockHttpClient::class)) {
            self::markTestSkipped('symfony/http-client is not installed.');
        }
        $report = self::php([__DIR__.'/Fixtures/load-all.php', self::autoload(), \dirname(__DIR__), \Symfony\Component\HttpClient\MockHttpClient::class]);

        self::assertContains(\Symfony\Component\HttpClient\MockHttpClient::class, self::foreign(self::strings($report['symbols'])));
        self::assertNotEmpty(preg_grep('~/symfony/http-client/~', self::outside(self::strings($report['files']), self::strings($report['eager']))));
    }

    /** @param array<mixed> $report */
    private static function at(array $report, string ...$path): mixed
    {
        foreach ($path as $key) {
            $report = \is_array($report) && \array_key_exists($key, $report) ? $report[$key] : null;
        }

        return $report;
    }

    /** @return list<string> the strings of a list (null and other values left out) */
    private static function strings(mixed $list): array
    {
        return \is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }

    /**
     * @param list<string> $symbols
     *
     * @return list<string>
     */
    private static function foreign(array $symbols): array
    {
        return array_values(array_filter($symbols, static fn (string $symbol) => 1 === preg_match(self::FOREIGN, $symbol)));
    }

    /**
     * The files loaded from outside the package but those allowed - and those
     * Composer includes for every package at start (autoload "files").
     *
     * @param list<string> $files
     * @param list<string> $eager
     *
     * @return list<string>
     */
    private static function outside(array $files, array $eager): array
    {
        $root = realpath(\dirname(__DIR__)).'/';
        $eager = array_map('dirname', array_filter($eager));

        return array_values(array_filter($files, static fn (string $file) => (!str_starts_with($file, $root) || str_starts_with($file, $root.'vendor/')) && !\in_array(\dirname($file), $eager, true) && !preg_match(self::ALLOWED_FILES, $file)));
    }

    /**
     * The autoloader that knows omnidns and its tests - the package's own or
     * its harness's: installed in an application, whose vendor/ loads what
     * the application needs at start, the check says nothing of omnidns.
     */
    private static function autoload(): string
    {
        $package = (string) realpath(\dirname(__DIR__));
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if ($loader->findFile(Registry::class) && $loader->findFile(self::class)) {
                if (str_starts_with($package.'/', (string) realpath($vendor).'/')) {
                    self::markTestSkipped('omnidns is installed in an application here: the bare check is the package\'s own.');
                }

                return $vendor.'/autoload.php';
            }
        }
        self::markTestSkipped('No autoloader knows omnidns\'s tests.');
    }

    /**
     * @param list<string> $arguments
     *
     * @return array<string, mixed>
     */
    private static function php(array $arguments): array
    {
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNIDNS_AUTOLOAD' => self::autoload()] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertSame(0, $status, $err.$out);
        self::assertIsArray($report, $err.$out);

        /** @var array<string, mixed> $report */
        return $report;
    }
}
