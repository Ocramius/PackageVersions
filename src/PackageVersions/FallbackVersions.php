<?php

declare(strict_types=1);

namespace PackageVersions;

use Generator;
use OutOfBoundsException;
use UnexpectedValueException;
use function array_key_exists;
use function array_merge;
use function file_exists;
use function file_get_contents;
use function iterator_to_array;
use function json_decode;
use function json_encode;
use function sprintf;

/**
 * @internal
 *
 * This is a fallback for {@see \PackageVersions\Versions::getVersion()}
 * Do not use this class directly: it is intended to be only used when
 * {@see \PackageVersions\Versions} fails to be generated, which typically
 * happens when running composer with `--no-scripts` flag)
 */
final class FallbackVersions
{
    public const ROOT_PACKAGE_NAME = 'unknown/root-package@UNKNOWN';

    private function __construct()
    {
    }

    /**
     * @throws OutOfBoundsException If a version cannot be located.
     * @throws UnexpectedValueException If the composer.lock file could not be located.
     */
    public static function getVersion(string $packageName) : string
    {
        $versions = iterator_to_array(self::getVersions(self::getComposerLockPath()));

        if (! array_key_exists($packageName, $versions)) {
            throw new OutOfBoundsException(
                'Required package "' . $packageName . '" is not installed: check your ./vendor/composer/installed.json and/or ./composer.lock files'
            );
        }

        return $versions[$packageName];
    }

    /**
     * @throws UnexpectedValueException
     */
    private static function getComposerLockPath() : string
    {
        $checkedPaths = [
            // The top-level project's ./vendor/composer/installed.json
            getcwd() . '/vendor/composer/installed.json',
            // The top-level project's ./composer.lock
            getcwd() . '/composer.lock',
            // This package's composer.lock
            __DIR__ . '/../../composer.lock',
        ];

        foreach ($checkedPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new UnexpectedValueException(sprintf(
            'PackageVersions could not locate the `vendor/composer/installed.json` or your `composer.lock` '
            . 'location. This is assumed to be in %s. If you customized your composer vendor directory and ran composer '
            . 'installation with --no-scripts or if you deployed without the required composer files, then you are on '
            . 'your own, and we can\'t really help you. Fix your shit and cut the tooling some slack.',
            json_encode($checkedPaths)
        ));
    }

    private static function getVersions(string $composerLockFile) : Generator
    {
        $lockData = json_decode(file_get_contents($composerLockFile), true);

        if (array_key_exists('content-hash', $lockData)) {
            // assume a composer.lock file and merge the packages and packages-dev into an array
            $lockData = array_merge($lockData['packages'], $lockData['packages-dev'] ?? []);
        }

        foreach ($lockData as $package) {
            yield $package['name'] => $package['version'] . '@' . (
                $package['source']['reference'] ?? $package['dist']['reference'] ?? ''
            );
        }

        yield self::ROOT_PACKAGE_NAME;
    }
}
