<?php

declare(strict_types=1);

namespace PackageVersionsTest;

use OutOfBoundsException;
use PackageVersions\FallbackVersions;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use function array_merge;
use function file_get_contents;
use function json_decode;
use function json_encode;
use function realpath;
use function rename;
use function uniqid;

/**
 * @covers \PackageVersions\FallbackVersions
 */
final class FallbackVersionsTest extends TestCase
{
    public function testWillFailWithoutValidComposerLockLocation() : void
    {
        rename(__DIR__ . '/../../vendor/composer/installed.json', __DIR__ . '/../../vendor/composer/installed.json.backup');
        rename(__DIR__ . '/../../composer.lock', __DIR__ . '/../../composer.lock.backup');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageRegExp(
            '@PackageVersions could not locate the `vendor/composer/installed\.json` or your `composer\.lock` '
            . 'location\. This is assumed to be in \[[^]]+?\]\. If you customized your composer vendor directory and ran composer '
            . 'installation with --no-scripts or if you deployed without the required composer files, then you are on '
            . 'your own, and we can\'t really help you\. Fix your shit and cut the tooling some slack\.@'
        );

        FallbackVersions::getVersion('phpunit/phpunit');
    }

    public function testValidVersions() : void
    {
        $lockData = json_decode(file_get_contents(__DIR__ . '/../../composer.lock'), true);

        $packages = array_merge($lockData['packages'], $lockData['packages-dev']);

        self::assertNotEmpty($packages);

        foreach ($packages as $package) {
            self::assertSame(
                $package['version'] . '@' . $package['source']['reference'],
                FallbackVersions::getVersion($package['name'])
            );
        }
    }

    public function testInvalidVersionsAreRejected() : void
    {
        $this->expectException(OutOfBoundsException::class);

        FallbackVersions::getVersion(uniqid('', true) . '/' . uniqid('', true));
    }

    protected function tearDown()
    {
        if (file_exists(__DIR__ . '/../../vendor/composer/installed.json.backup')) {
            rename(__DIR__ . '/../../vendor/composer/installed.json.backup', __DIR__ . '/../../vendor/composer/installed.json');
        }

        if (file_exists(__DIR__ . '/../../composer.lock.backup')) {
            rename(__DIR__ . '/../../composer.lock.backup', __DIR__ . '/../../composer.lock');
        }
    }
}
