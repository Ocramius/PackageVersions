<?php

declare(strict_types=1);

namespace PackageVersionsTest;

use OutOfBoundsException;
use PackageVersions\Versions;
use PHPUnit\Framework\TestCase;

use function array_merge;
use function file_get_contents;
use function json_decode;
use function uniqid;

/** @covers \PackageVersions\Versions */
final class VersionsTest extends TestCase
{
    public function testValidVersions(): void
    {
        $lockData = json_decode(file_get_contents(__DIR__ . '/../../composer.lock'), true);

        $packages = array_merge($lockData['packages'], $lockData['packages-dev']);

        self::assertNotEmpty($packages);

        foreach ($packages as $package) {
            self::assertSame(
                $package['version'] . '@' . $package['source']['reference'],
                Versions::getVersion($package['name']),
            );
        }
    }

    /** @group #148 */
    public function testCanRetrieveRootPackageVersion(): void
    {
        /** @psalm-suppress DeprecatedConstant */
        self::assertMatchesRegularExpression('/^.+\@[0-9a-f]+$/', Versions::getVersion(Versions::rootPackageName()));
    }

    /** @group #153 */
    public function testCanRetrieveRootPackageName(): void
    {
        self::assertMatchesRegularExpression('/^[a-z0-9\\-]+\\/[a-z0-9\\-]+$/', Versions::rootPackageName());
    }

    public function testInvalidVersionsAreRejected(): void
    {
        $this->expectException(OutOfBoundsException::class);

        /** @psalm-suppress ArgumentTypeCoercion we are explicitly testing for something not allowed by the type system */
        Versions::getVersion(uniqid('', true) . '/' . uniqid('', true));
    }
}
