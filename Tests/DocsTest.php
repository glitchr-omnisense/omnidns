<?php

namespace Omnidns\Tests;

use PHPUnit\Framework\TestCase;

/** docs/installation.md's PHP block, run as it is written (its autoloader the one of this run). */
final class DocsTest extends TestCase
{
    public function testTheInstallationPageRunsAsWritten(): void
    {
        $text = (string) file_get_contents(__DIR__.'/../docs/installation.md');
        self::assertSame(1, preg_match('~```php\n(.*?)```~s', $text, $match));
        $block = $match[1] ?? '';
        $autoload = null;
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if ($loader->findFile('Omnidns\Testing\MemoryFactory')) {
                $autoload = $vendor.'/autoload.php';
                break;
            }
        }
        if (null === $autoload) {
            self::markTestSkipped('No autoloader knows omnidns.');
        }
        $code = str_replace("require 'vendor/autoload.php';", 'require '.var_export($autoload, true).';', $block, $replaced);
        self::assertSame(1, $replaced);
        $script = (string) tempnam(sys_get_temp_dir(), 'omnidns-docs');
        file_put_contents($script, $code);
        try {
            $process = proc_open([\PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $out = (string) stream_get_contents($pipes[1]);
            $err = (string) stream_get_contents($pipes[2]);
            self::assertSame(0, proc_close($process), $err.$out);
        } finally {
            unlink($script);
        }

        $lines = ['glitchr-example.org: free, 8.39 EUR', 'register done, ends 2028-01-01', 'glitchr-example.org A 192.0.2.10', 'glitchr-example.org MX 10 mx.example.net.'];
        self::assertSame(implode("\n", $lines)."\n", $out);
        foreach ($lines as $line) {
            self::assertStringContainsString("\n// $line\n", $block, 'the page says what it prints');
        }
    }
}
