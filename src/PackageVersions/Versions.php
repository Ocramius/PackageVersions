<?php

declare(strict_types=1);

namespace PackageVersions;

use Composer\InstalledVersions;
use OutOfBoundsException;

/**
 * This is a stub class: it is in place only for scenarios where PackageVersions
 * is installed with a `--no-scripts` flag, in which scenarios the Versions class
 * is not being replaced.
 *
 * If you are reading this docBlock inside your `vendor/` dir, then this means
 * that PackageVersions didn't correctly install, and is in "fallback" mode.
 */
final class Versions
{
    /**
     * @deprecated please use {@see self::rootPackageName()} instead.
     *             This constant will be removed in version 2.0.0.
     */
    public const ROOT_PACKAGE_NAME = 'unknown/root-package@UNKNOWN';

    private function __construct()
    {
    }

    /**
     * @psalm-pure
     *
     * @psalm-suppress ImpureMethodCall we know that {@see InstalledVersions} interaction does not
     *                                  cause any side effects here.
     */
    public static function rootPackageName() : string
    {
        return InstalledVersions::getRootPackage()['name'];
    }

    /**
     * @throws OutOfBoundsException if a version cannot be located.
     *
     * @psalm-pure
     *
     * @psalm-suppress ImpureMethodCall we know that {@see InstalledVersions} interaction does not
     *                                  cause any side effects here.
     */
    public static function getVersion(string $packageName) : string
    {
        /** @psalm-suppress DeprecatedConstant */
        if ($packageName === self::ROOT_PACKAGE_NAME) {
            $rootPackage = InstalledVersions::getRootPackage();

            return $rootPackage['pretty_version'] . '@' . $rootPackage['reference'];
        }

        return InstalledVersions::getPrettyVersion($packageName)
            . '@' . InstalledVersions::getReference($packageName);
    }
}
