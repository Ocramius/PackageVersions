<?php

namespace PackageVersions;

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
    const ROOT_PACKAGE_NAME = FallbackVersions::ROOT_PACKAGE_NAME;
    const VERSIONS = [];

    private function __construct()
    {
    }

    /**
     * @throws \OutOfBoundsException if a version cannot be located
     * @throws \UnexpectedValueException if the composer.lock file could not be located
     */
    public static function getVersion(string $packageName) : string
    {
        return FallbackVersions::getVersion($packageName);
    }
}
