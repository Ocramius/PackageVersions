<?php

namespace PackageVersionsTest;

use PackageVersions\FallbackVersions;
use PackageVersions\Versions;
use PHPUnit_Framework_TestCase;

/**
 * @covers \PackageVersions\FallbackVersions
 */
final class FallbackVersionsTest extends PHPUnit_Framework_TestCase
{
    public function testWillFailWithoutValidComposerLockLocation()
    {
        rename(__DIR__ . '/../../composer.lock', __DIR__ . '/../../composer.lock.backup');

        try {
            FallbackVersions::getVersion('phpunit/phpunit');

            self::fail('An exception was supposed to be thrown');
        } catch (\UnexpectedValueException $lockFileNotFound) {
            $srcDir = realpath(__DIR__ . '/../../src/PackageVersions');

            self::assertSame(
                'PackageVersions could not locate your `composer.lock` location. '
                . 'This is assumed to be in '
                . json_encode([$srcDir . '/../../../../../composer.lock', $srcDir . '/../../composer.lock'])
                . '. If you customized your composer vendor directory and ran composer installation with --no-scripts, '
                . 'then you are on your own, and we can\'t really help you. '
                . 'Fix your shit and cut the tooling some slack.',
                $lockFileNotFound->getMessage()
            );
        } finally {
            rename(__DIR__ . '/../../composer.lock.backup', __DIR__ . '/../../composer.lock');
        }
    }

    public function testValidVersions()
    {
        $lockData = json_decode(file_get_contents(__DIR__ . '/../../composer.lock'), true);

        $packages = array_merge($lockData['packages'], $lockData['packages-dev']);

        self::assertNotEmpty($packages);

        foreach ($packages as $package) {
            self::assertSame(
                $package['version'] . '@' . $package['source']['reference'],
                Versions::getVersion($package['name'])
            );
        }
    }

    public function testInvalidVersionsAreRejected()
    {
        $this->expectException(\OutOfBoundsException::class);

        Versions::getVersion(uniqid('', true) . '/' . uniqid('', true));
    }
}
