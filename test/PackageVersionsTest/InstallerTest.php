<?php

declare(strict_types=1);

namespace PackageVersionsTest;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\Locker;
use Composer\Package\RootAliasPackage;
use Composer\Package\RootPackage;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use PackageVersions\Installer;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use const PHP_OS;
use function array_filter;
use function array_map;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function in_array;
use function is_dir;
use function mkdir;
use function preg_match_all;
use function realpath;
use function rmdir;
use function scandir;
use function sprintf;
use function strpos;
use function substr;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * @covers \PackageVersions\Installer
 */
final class InstallerTest extends TestCase
{
    /** @var Composer&MockObject */
    private $composer;

    /** @var EventDispatcher&MockObject */
    private $eventDispatcher;

    /** @var IOInterface&MockObject */
    private $io;

    /** @var Installer */
    private $installer;

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    protected function setUp() : void
    {
        parent::setUp();

        $this->installer       = new Installer();
        $this->io              = $this->createMock(IOInterface::class);
        $this->composer        = $this->createMock(Composer::class);
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);

        $this->composer->expects(self::any())->method('getEventDispatcher')->willReturn($this->eventDispatcher);
    }

    public function testGetSubscribedEvents() : void
    {
        $events = Installer::getSubscribedEvents();

        self::assertSame(
            ['post-autoload-dump' => 'dumpVersionsClass'],
            $events
        );

        foreach ($events as $callback) {
            self::assertIsCallable([$this->installer, $callback]);
        }
    }

    public function testDumpVersionsClassIfExistingFileIsNotWritable() : void
    {
        $config            = $this->createMock(Config::class);
        $locker            = $this->createMock(Locker::class);
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $installManager    = $this->createMock(InstallationManager::class);
        $repository        = $this->createMock(InstalledRepositoryInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

        /** @noinspection MkdirRaceConditionInspection */
        mkdir($expectedPath, 0777, true);

        $expectedFileName = $expectedPath . '/Versions.php';
        file_put_contents($expectedFileName, 'NOT PHP!');
        chmod($expectedFileName, 0444);

        $locker
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name'    => 'ocramius/package-versions',
                        'version' => '1.0.0',
                    ],
                ],
            ]);

        $repositoryManager->method('getLocalRepository')->willReturn($repository);

        $this->composer->method('getConfig')->willReturn($config);
        $this->composer->method('getLocker')->willReturn($locker);
        $this->composer->method('getRepositoryManager')->willReturn($repositoryManager);
        $this->composer->method('getPackage')->willReturn($this->getRootPackageMock());
        $this->composer->method('getInstallationManager')->willReturn($installManager);

        $config->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::dumpVersionsClass(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        self::assertStringStartsWith('<?php', file_get_contents($expectedFileName));

        $this->rmDir($vendorDir);
    }

    public function testDumpVersionsClass() : void
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

        /** @noinspection MkdirRaceConditionInspection */
        mkdir($expectedPath, 0777, true);

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name'    => 'ocramius/package-versions',
                        'version' => '1.0.0',
                    ],
                    [
                        'name'    => 'foo/bar',
                        'version' => '1.2.3',
                        'source'  => ['reference' => 'abc123'],
                    ],
                    [
                        'name'    => 'baz/tab',
                        'version' => '4.5.6',
                        'source'  => ['reference' => 'def456'],
                    ],
                ],
                'packages-dev' => [
                    [
                        'name'    => 'tar/taz',
                        'version' => '7.8.9',
                        'source'  => ['reference' => 'ghi789'],
                    ],
                ],
            ]);

        $repositoryManager->expects(self::any())->method('getLocalRepository')->willReturn($repository);

        $this->composer->expects(self::any())->method('getConfig')->willReturn($config);
        $this->composer->expects(self::any())->method('getLocker')->willReturn($locker);
        $this->composer->expects(self::any())->method('getRepositoryManager')->willReturn($repositoryManager);
        $this->composer->expects(self::any())->method('getPackage')->willReturn($this->getRootPackageMock());
        $this->composer->expects(self::any())->method('getInstallationManager')->willReturn($installManager);

        $config->expects(self::any())->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::dumpVersionsClass(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        $expectedSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace PackageVersions;

/**
 * This class is generated by ocramius/package-versions, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
final class Versions
{
    public const ROOT_PACKAGE_NAME = 'root/package';
    /**
     * Array of all available composer packages.
     * Dont read this array from your calling code, but use the \PackageVersions\Versions::getVersion() method instead.
     *
     * @var array<string, string>
     * @internal
     */
    public const VERSIONS          = array (
  'ocramius/package-versions' => '1.0.0@',
  'foo/bar' => '1.2.3@abc123',
  'baz/tab' => '4.5.6@def456',
  'tar/taz' => '7.8.9@ghi789',
  'some-replaced/package' => '1.3.5@aaabbbcccddd',
  'root/package' => '1.3.5@aaabbbcccddd',
);

    private function __construct()
    {
    }

    /**
     * @throws \OutOfBoundsException If a version cannot be located.
     *
     * @psalm-param key-of<self::VERSIONS> $packageName
     */
    public static function getVersion(string $packageName) : string
    {
        if (isset(self::VERSIONS[$packageName])) {
            return self::VERSIONS[$packageName];
        }

        throw new \OutOfBoundsException(
            'Required package "' . $packageName . '" is not installed: check your ./vendor/composer/installed.json and/or ./composer.lock files'
        );
    }
}

PHP;

        self::assertSame($expectedSource, file_get_contents($expectedPath . '/Versions.php'));

        $this->rmDir($vendorDir);
    }

    public function testDumpVersionsClassNoDev() : void
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

        /** @noinspection MkdirRaceConditionInspection */
        mkdir($expectedPath, 0777, true);

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name'    => 'ocramius/package-versions',
                        'version' => '1.0.0',
                    ],
                    [
                        'name'    => 'foo/bar',
                        'version' => '1.2.3',
                        'source'  => ['reference' => 'abc123'],
                    ],
                    [
                        'name'    => 'baz/tab',
                        'version' => '4.5.6',
                        'source'  => ['reference' => 'def456'],
                    ],
                ],
            ]);

        $repositoryManager->expects(self::any())->method('getLocalRepository')->willReturn($repository);

        $this->composer->expects(self::any())->method('getConfig')->willReturn($config);
        $this->composer->expects(self::any())->method('getLocker')->willReturn($locker);
        $this->composer->expects(self::any())->method('getRepositoryManager')->willReturn($repositoryManager);
        $this->composer->expects(self::any())->method('getPackage')->willReturn($this->getRootPackageMock());
        $this->composer->expects(self::any())->method('getInstallationManager')->willReturn($installManager);

        $config->expects(self::any())->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::dumpVersionsClass(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        $expectedSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace PackageVersions;

/**
 * This class is generated by ocramius/package-versions, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
final class Versions
{
    public const ROOT_PACKAGE_NAME = 'root/package';
    /**
     * Array of all available composer packages.
     * Dont read this array from your calling code, but use the \PackageVersions\Versions::getVersion() method instead.
     *
     * @var array<string, string>
     * @internal
     */
    public const VERSIONS          = array (
  'ocramius/package-versions' => '1.0.0@',
  'foo/bar' => '1.2.3@abc123',
  'baz/tab' => '4.5.6@def456',
  'some-replaced/package' => '1.3.5@aaabbbcccddd',
  'root/package' => '1.3.5@aaabbbcccddd',
);

    private function __construct()
    {
    }

    /**
     * @throws \OutOfBoundsException If a version cannot be located.
     *
     * @psalm-param key-of<self::VERSIONS> $packageName
     */
    public static function getVersion(string $packageName) : string
    {
        if (isset(self::VERSIONS[$packageName])) {
            return self::VERSIONS[$packageName];
        }

        throw new \OutOfBoundsException(
            'Required package "' . $packageName . '" is not installed: check your ./vendor/composer/installed.json and/or ./composer.lock files'
        );
    }
}

PHP;

        self::assertSame($expectedSource, file_get_contents($expectedPath . '/Versions.php'));

        $this->rmDir($vendorDir);
    }

    /**
     * @throws RuntimeException
     *
     * @group #12
     */
    public function testDumpVersionsWithoutPackageSourceDetails() : void
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

        /** @noinspection MkdirRaceConditionInspection */
        mkdir($expectedPath, 0777, true);

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name'    => 'ocramius/package-versions',
                        'version' => '1.0.0',
                    ],
                    [
                        'name'    => 'foo/bar',
                        'version' => '1.2.3',
                        'dist'  => ['reference' => 'abc123'], // version defined in the dist, this time
                    ],
                    [
                        'name'    => 'baz/tab',
                        'version' => '4.5.6', // source missing
                    ],
                ],
            ]);

        $repositoryManager->expects(self::any())->method('getLocalRepository')->willReturn($repository);

        $this->composer->expects(self::any())->method('getConfig')->willReturn($config);
        $this->composer->expects(self::any())->method('getLocker')->willReturn($locker);
        $this->composer->expects(self::any())->method('getRepositoryManager')->willReturn($repositoryManager);
        $this->composer->expects(self::any())->method('getPackage')->willReturn($this->getRootPackageMock());
        $this->composer->expects(self::any())->method('getInstallationManager')->willReturn($installManager);

        $config->expects(self::any())->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::dumpVersionsClass(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        $expectedSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace PackageVersions;

/**
 * This class is generated by ocramius/package-versions, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
final class Versions
{
    public const ROOT_PACKAGE_NAME = 'root/package';
    /**
     * Array of all available composer packages.
     * Dont read this array from your calling code, but use the \PackageVersions\Versions::getVersion() method instead.
     *
     * @var array<string, string>
     * @internal
     */
    public const VERSIONS          = array (
  'ocramius/package-versions' => '1.0.0@',
  'foo/bar' => '1.2.3@abc123',
  'baz/tab' => '4.5.6@',
  'some-replaced/package' => '1.3.5@aaabbbcccddd',
  'root/package' => '1.3.5@aaabbbcccddd',
);

    private function __construct()
    {
    }

    /**
     * @throws \OutOfBoundsException If a version cannot be located.
     *
     * @psalm-param key-of<self::VERSIONS> $packageName
     */
    public static function getVersion(string $packageName) : string
    {
        if (isset(self::VERSIONS[$packageName])) {
            return self::VERSIONS[$packageName];
        }

        throw new \OutOfBoundsException(
            'Required package "' . $packageName . '" is not installed: check your ./vendor/composer/installed.json and/or ./composer.lock files'
        );
    }
}

PHP;

        self::assertSame($expectedSource, file_get_contents($expectedPath . '/Versions.php'));

        $this->rmDir($vendorDir);
    }

    /**
     * @throws RuntimeException
     *
     * @dataProvider rootPackageProvider
     */
    public function testDumpsVersionsClassToSpecificLocation(RootPackageInterface $rootPackage, bool $inVendor) : void
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true) . '/vendor';

        /** @noinspection MkdirRaceConditionInspection */
        mkdir($vendorDir, 0777, true);

        /** @noinspection RealpathInSteamContextInspection */
        $expectedPath = $inVendor
            ? $vendorDir . '/ocramius/package-versions/src/PackageVersions'
            : realpath($vendorDir . '/..') . '/src/PackageVersions';

        /** @noinspection MkdirRaceConditionInspection */
        mkdir($expectedPath, 0777, true);

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name'    => 'ocramius/package-versions',
                        'version' => '1.0.0',
                    ],
                ],
                'packages-dev' => [],
            ]);

        $repositoryManager->expects(self::any())->method('getLocalRepository')->willReturn($repository);

        $this->composer->expects(self::any())->method('getConfig')->willReturn($config);
        $this->composer->expects(self::any())->method('getLocker')->willReturn($locker);
        $this->composer->expects(self::any())->method('getRepositoryManager')->willReturn($repositoryManager);
        $this->composer->expects(self::any())->method('getPackage')->willReturn($rootPackage);
        $this->composer->expects(self::any())->method('getInstallationManager')->willReturn($installManager);

        $config->expects(self::any())->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::dumpVersionsClass(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        self::assertStringMatchesFormat(
            '%Aclass Versions%A1.2.3@%A',
            file_get_contents($expectedPath . '/Versions.php')
        );

        $this->rmDir($vendorDir);
    }

    /**
     * @return bool[][]|RootPackageInterface[][] the root package and whether the versions class is to be generated in
     *                                           the vendor dir or not
     */
    public function rootPackageProvider() : array
    {
        $baseRootPackage                         = new RootPackage('root/package', '1.2.3', '1.2.3');
        $aliasRootPackage                        = new RootAliasPackage($baseRootPackage, '1.2.3', '1.2.3');
        $indirectAliasRootPackage                = new RootAliasPackage($aliasRootPackage, '1.2.3', '1.2.3');
        $packageVersionsRootPackage              = new RootPackage('ocramius/package-versions', '1.2.3', '1.2.3');
        $aliasPackageVersionsRootPackage         = new RootAliasPackage($packageVersionsRootPackage, '1.2.3', '1.2.3');
        $indirectAliasPackageVersionsRootPackage = new RootAliasPackage(
            $aliasPackageVersionsRootPackage,
            '1.2.3',
            '1.2.3'
        );

        return [
            'root package is not ocramius/package-versions' => [
                $baseRootPackage,
                true,
            ],
            'alias root package is not ocramius/package-versions' => [
                $aliasRootPackage,
                true,
            ],
            'indirect alias root package is not ocramius/package-versions' => [
                $indirectAliasRootPackage,
                true,
            ],
            'root package is ocramius/package-versions' => [
                $packageVersionsRootPackage,
                false,
            ],
            'alias root package is ocramius/package-versions' => [
                $aliasPackageVersionsRootPackage,
                false,
            ],
            'indirect alias root package is ocramius/package-versions' => [
                $indirectAliasPackageVersionsRootPackage,
                false,
            ],
        ];
    }

    public function testVersionsAreNotDumpedIfPackageVersionsNotExplicitlyRequired() : void
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);
        $package           = $this->createMock(RootPackageInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

        /** @noinspection MkdirRaceConditionInspection */
        mkdir($expectedPath, 0777, true);

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name'    => 'foo/bar',
                        'version' => '1.2.3',
                        'dist'  => ['reference' => 'abc123'], // version defined in the dist, this time
                    ],
                    [
                        'name'    => 'baz/tab',
                        'version' => '4.5.6', // source missing
                    ],
                ],
            ]);

        $repositoryManager->expects(self::any())->method('getLocalRepository')->willReturn($repository);

        $this->composer->expects(self::any())->method('getConfig')->willReturn($config);
        $this->composer->expects(self::any())->method('getLocker')->willReturn($locker);
        $this->composer->expects(self::any())->method('getRepositoryManager')->willReturn($repositoryManager);
        $this->composer->expects(self::any())->method('getPackage')->willReturn($package);
        $this->composer->expects(self::any())->method('getInstallationManager')->willReturn($installManager);

        $package->expects(self::any())->method('getName')->willReturn('root/package');
        $package->expects(self::any())->method('getVersion')->willReturn('1.3.5');
        $package->expects(self::any())->method('getSourceReference')->willReturn('aaabbbcccddd');
        $package->expects(self::any())->method('getReplaces')->willReturn([]);

        $config->expects(self::any())->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::dumpVersionsClass(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        self::assertFileNotExists($expectedPath . '/Versions.php');

        $this->rmDir($vendorDir);
    }

    /**
     * @group #41
     * @group #46
     */
    public function testVersionsAreNotDumpedIfPackageIsScheduledForRemoval() : void
    {
        $config  = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker  = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $package = $this->createMock(RootPackageInterface::class);
        $package->expects(self::any())->method('getReplaces')->willReturn([]);
        $vendorDir    = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);
        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name'    => 'ocramius/package-versions',
                        'version' => '1.0.0',
                    ],
                ],
            ]);

        $package->expects(self::any())->method('getName')->willReturn('root/package');

        $this->composer->expects(self::any())->method('getConfig')->willReturn($config);
        $this->composer->expects(self::any())->method('getLocker')->willReturn($locker);
        $this->composer->expects(self::any())->method('getPackage')->willReturn($package);

        $config->expects(self::any())->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::dumpVersionsClass(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        self::assertFileNotExists($expectedPath . '/Versions.php');
        self::assertFileNotExists($expectedPath . '/Versions.php');
    }

    public function testGeneratedVersionFileAccessRights() : void
    {
        if (strpos(PHP_OS, 'WIN') === 0) {
            $this->markTestSkipped('Windows is kinda "meh" at file access levels');
        }

        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);
        $package           = $this->createMock(RootPackageInterface::class);
        $package->expects(self::any())->method('getReplaces')->willReturn([]);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

        /** @noinspection MkdirRaceConditionInspection */
        mkdir($expectedPath, 0777, true);

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name'    => 'ocramius/package-versions',
                        'version' => '1.0.0',
                    ],
                ],
            ]);

        $repositoryManager->expects(self::any())->method('getLocalRepository')->willReturn($repository);

        $this->composer->expects(self::any())->method('getConfig')->willReturn($config);
        $this->composer->expects(self::any())->method('getLocker')->willReturn($locker);
        $this->composer->expects(self::any())->method('getRepositoryManager')->willReturn($repositoryManager);
        $this->composer->expects(self::any())->method('getPackage')->willReturn($package);
        $this->composer->expects(self::any())->method('getInstallationManager')->willReturn($installManager);

        $package->expects(self::any())->method('getName')->willReturn('root/package');
        $package->expects(self::any())->method('getVersion')->willReturn('1.3.5');
        $package->expects(self::any())->method('getSourceReference')->willReturn('aaabbbcccddd');

        $config->expects(self::any())->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::dumpVersionsClass(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        $filePath = $expectedPath . '/Versions.php';

        self::assertFileExists($filePath);
        self::assertSame('0664', substr(sprintf('%o', fileperms($filePath)), -4));

        $this->rmDir($vendorDir);
    }

    private function rmDir(string $directory) : void
    {
        if (! is_dir($directory)) {
            unlink($directory);

            return;
        }

        array_map(
            function ($item) use ($directory) : void {
                $this->rmDir($directory . '/' . $item);
            },
            array_filter(
                scandir($directory),
                static function (string $dirItem) {
                    return ! in_array($dirItem, ['.', '..'], true);
                }
            )
        );

        rmdir($directory);
    }

    /**
     * @group composer/composer#5237
     */
    public function testWillEscapeRegexParsingOfClassDefinitions() : void
    {
        self::assertSame(
            1,
            preg_match_all(
                '{^((?:final\s+)?(?:\s*))class\s+(\S+)}mi',
                file_get_contents((new ReflectionClass(Installer::class))->getFileName())
            )
        );
    }

    public function testGetVersionsIsNotNormalizedForRootPackage() : void
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

        /** @noinspection MkdirRaceConditionInspection */
        mkdir($expectedPath, 0777, true);

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name'    => 'ocramius/package-versions',
                        'version' => '1.0.0',
                    ],
                ],
            ]);

        $repositoryManager->expects(self::any())->method('getLocalRepository')->willReturn($repository);

        $this->composer->expects(self::any())->method('getConfig')->willReturn($config);
        $this->composer->expects(self::any())->method('getLocker')->willReturn($locker);
        $this->composer->expects(self::any())->method('getRepositoryManager')->willReturn($repositoryManager);
        $this->composer->expects(self::any())->method('getPackage')->willReturn($this->getRootPackageMock());
        $this->composer->expects(self::any())->method('getInstallationManager')->willReturn($installManager);

        $config->expects(self::any())->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::dumpVersionsClass(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        $expectedSource = <<<'PHP'
<?php

declare(strict_types=1);

namespace PackageVersions;

/**
 * This class is generated by ocramius/package-versions, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
final class Versions
{
    public const ROOT_PACKAGE_NAME = 'root/package';
    /**
     * Array of all available composer packages.
     * Dont read this array from your calling code, but use the \PackageVersions\Versions::getVersion() method instead.
     *
     * @var array<string, string>
     * @internal
     */
    public const VERSIONS          = array (
  'ocramius/package-versions' => '1.0.0@',
  'some-replaced/package' => '1.3.5@aaabbbcccddd',
  'root/package' => '1.3.5@aaabbbcccddd',
);

    private function __construct()
    {
    }

    /**
     * @throws \OutOfBoundsException If a version cannot be located.
     *
     * @psalm-param key-of<self::VERSIONS> $packageName
     */
    public static function getVersion(string $packageName) : string
    {
        if (isset(self::VERSIONS[$packageName])) {
            return self::VERSIONS[$packageName];
        }

        throw new \OutOfBoundsException(
            'Required package "' . $packageName . '" is not installed: check your ./vendor/composer/installed.json and/or ./composer.lock files'
        );
    }
}

PHP;

        self::assertSame($expectedSource, file_get_contents($expectedPath . '/Versions.php'));

        $this->rmDir($vendorDir);
    }

    private function getRootPackageMock() : RootPackageInterface
    {
        $package = $this->createMock(RootPackageInterface::class);
        $package->expects(self::any())->method('getName')->willReturn('root/package');
        $package->expects(self::any())->method('getPrettyVersion')->willReturn('1.3.5');
        $package->expects(self::any())->method('getSourceReference')->willReturn('aaabbbcccddd');

        $link = $this->createMock(Link::class);
        $link->expects(self::any())->method('getTarget')->willReturn('some-replaced/package');
        $link->expects(self::any())->method('getPrettyConstraint')->willReturn('self.version');

        $package->expects(self::any())->method('getReplaces')->willReturn([$link]);

        return $package;
    }
}
