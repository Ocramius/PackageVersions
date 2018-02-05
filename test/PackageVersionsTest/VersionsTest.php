<?php

namespace PackageVersionsTest;

use PackageVersions\Versions;
use PHPUnit\Framework\TestCase;

/**
 * @covers \PackageVersions\Versions
 *
 * @uses \PackageVersions\FallbackVersions
 */
final class VersionsTest extends TestCase
{
    public function testValidVersions() : void
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

    public function testInvalidVersionsAreRejected() : void
    {
        $this->expectException(\OutOfBoundsException::class);

        Versions::getVersion(uniqid('', true) . '/' . uniqid('', true));
    }
}
