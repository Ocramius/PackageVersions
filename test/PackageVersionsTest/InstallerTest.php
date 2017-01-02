<?php

namespace PackageVersionsTest;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\Locker;
use Composer\Package\RootAliasPackage;
use Composer\Package\RootPackage;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use PackageVersions\Installer;
use PHPUnit_Framework_TestCase;

/**
 * @covers \PackageVersions\Installer
 */
final class InstallerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Composer|\PHPUnit_Framework_MockObject_MockObject
     */
    private $composer;

    /**
     * @var EventDispatcher|\PHPUnit_Framework_MockObject_MockObject
     */
    private $eventDispatcher;

    /**
     * @var IOInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $io;

    /**
     * @var Installer
     */
    private $installer;

    /**
     * {@inheritDoc}
     *
     * @throws \PHPUnit_Framework_Exception
     */
    protected function setUp()
    {
        parent::setUp();

        $this->installer       = new Installer();
        $this->io              = $this->createMock(IOInterface::class);
        $this->composer        = $this->createMock(Composer::class);
        $this->eventDispatcher = $this->getMockBuilder(EventDispatcher::class)->disableOriginalConstructor()->getMock();

        $this->composer->expects(self::any())->method('getEventDispatcher')->willReturn($this->eventDispatcher);
    }

    public function testGetSubscribedEvents()
    {
        $events = Installer::getSubscribedEvents();

        self::assertSame(
            [
                'post-install-cmd' => 'dumpVersionsClass',
                'post-update-cmd'  => 'dumpVersionsClass',
            ],
            $events
        );

        foreach ($events as $callback) {
            self::assertInternalType('callable', [$this->installer, $callback]);
        }
    }

    public function testDumpVersionsClass()
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);
        $package           = $this->createMock(RootPackageInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

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
                        'source'  => [
                            'reference' => 'abc123',
                        ],
                    ],
                    [
                        'name'    => 'baz/tab',
                        'version' => '4.5.6',
                        'source'  => [
                            'reference' => 'def456',
                        ],
                    ],
                ],
                'packages-dev' => [
                    [
                        'name'    => 'tar/taz',
                        'version' => '7.8.9',
                        'source'  => [
                            'reference' => 'ghi789',
                        ],
                    ]
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

        $expectedSource = <<<'PHP'
<?php

namespace PackageVersions;

/**
 * This class is generated by ocramius/package-versions, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
final class Versions
{
    const VERSIONS = array (
  'ocramius/package-versions' => '1.0.0@',
  'foo/bar' => '1.2.3@abc123',
  'baz/tab' => '4.5.6@def456',
  'tar/taz' => '7.8.9@ghi789',
  'root/package' => '1.3.5@aaabbbcccddd',
);

    private function __construct()
    {
    }

    /**
     * @throws \OutOfBoundsException if a version cannot be located
     */
    public static function getVersion(string $packageName) : string
    {
        if (! isset(self::VERSIONS[$packageName])) {
            throw new \OutOfBoundsException(
                'Required package "' . $packageName . '" is not installed: cannot detect its version'
            );
        }

        return self::VERSIONS[$packageName];
    }
}

PHP;

        self::assertSame($expectedSource, file_get_contents($expectedPath . '/Versions.php'));

        $this->rmDir($vendorDir);
    }

    public function testDumpVersionsClassNoDev()
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);
        $package           = $this->createMock(RootPackageInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

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
                        'source'  => [
                            'reference' => 'abc123',
                        ],
                    ],
                    [
                        'name'    => 'baz/tab',
                        'version' => '4.5.6',
                        'source'  => [
                            'reference' => 'def456',
                        ],
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

        $expectedSource = <<<'PHP'
<?php

namespace PackageVersions;

/**
 * This class is generated by ocramius/package-versions, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
final class Versions
{
    const VERSIONS = array (
  'ocramius/package-versions' => '1.0.0@',
  'foo/bar' => '1.2.3@abc123',
  'baz/tab' => '4.5.6@def456',
  'root/package' => '1.3.5@aaabbbcccddd',
);

    private function __construct()
    {
    }

    /**
     * @throws \OutOfBoundsException if a version cannot be located
     */
    public static function getVersion(string $packageName) : string
    {
        if (! isset(self::VERSIONS[$packageName])) {
            throw new \OutOfBoundsException(
                'Required package "' . $packageName . '" is not installed: cannot detect its version'
            );
        }

        return self::VERSIONS[$packageName];
    }
}

PHP;

        self::assertSame($expectedSource, file_get_contents($expectedPath . '/Versions.php'));

        $this->rmDir($vendorDir);
    }

    /**
     * @group #12
     *
     * @throws \RuntimeException
     */
    public function testDumpVersionsWithoutPackageSourceDetails()
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);
        $package           = $this->createMock(RootPackageInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

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
                        'dist'  => [
                            'reference' => 'abc123', // version defined in the dist, this time
                        ],
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

        $config->expects(self::any())->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::dumpVersionsClass(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        $expectedSource = <<<'PHP'
<?php

namespace PackageVersions;

/**
 * This class is generated by ocramius/package-versions, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
final class Versions
{
    const VERSIONS = array (
  'ocramius/package-versions' => '1.0.0@',
  'foo/bar' => '1.2.3@abc123',
  'baz/tab' => '4.5.6@',
  'root/package' => '1.3.5@aaabbbcccddd',
);

    private function __construct()
    {
    }

    /**
     * @throws \OutOfBoundsException if a version cannot be located
     */
    public static function getVersion(string $packageName) : string
    {
        if (! isset(self::VERSIONS[$packageName])) {
            throw new \OutOfBoundsException(
                'Required package "' . $packageName . '" is not installed: cannot detect its version'
            );
        }

        return self::VERSIONS[$packageName];
    }
}

PHP;

        self::assertSame($expectedSource, file_get_contents($expectedPath . '/Versions.php'));

        $this->rmDir($vendorDir);
    }

    /**
     * @dataProvider rootPackageProvider
     *
     * @param RootPackageInterface $rootPackage
     * @param bool                 $inVendor
     *
     * @throws \RuntimeException
     */
    public function testDumpsVersionsClassToSpecificLocation(RootPackageInterface $rootPackage, bool $inVendor)
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);
        
        $vendorDir = $tmpDir . '/vendor';

        $expectedPath = $inVendor
            ? $vendorDir . '/ocramius/package-versions/src/PackageVersions'
            : realpath($vendorDir . '/..') . '/src/PackageVersions';

        mkdir($expectedPath, 0777, true);

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name'    => 'ocramius/package-versions',
                        'version' => '1.0.0',
                    ]
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

        $this->rmDir($tmpDir);
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
                true
            ],
            'alias root package is not ocramius/package-versions' => [
                $aliasRootPackage,
                true
            ],
            'indirect alias root package is not ocramius/package-versions' => [
                $indirectAliasRootPackage,
                true
            ],
            'root package is ocramius/package-versions' => [
                $packageVersionsRootPackage,
                false
            ],
            'alias root package is ocramius/package-versions' => [
                $aliasPackageVersionsRootPackage,
                false
            ],
            'indirect alias root package is ocramius/package-versions' => [
                $indirectAliasPackageVersionsRootPackage,
                false
            ],
        ];
    }

    public function testVersionsAreNotDumpedIfPackageVersionsNotExplicitlyRequired()
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->createMock(InstalledRepositoryInterface::class);
        $package           = $this->createMock(RootPackageInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true);

        $expectedPath = $vendorDir . '/ocramius/package-versions/src/PackageVersions';

        mkdir($expectedPath, 0777, true);

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                 'packages' => [
                     [
                         'name'    => 'foo/bar',
                         'version' => '1.2.3',
                         'dist'  => [
                             'reference' => 'abc123', // version defined in the dist, this time
                         ],
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
     * @param string $directory
     *
     * @return void
     */
    private function rmDir(string $directory)
    {
        if (! is_dir($directory)) {
            unlink($directory);

            return;
        }

        array_map(
            function ($item) use ($directory) {
                $this->rmDir($directory . '/' . $item);
            },
            array_filter(
                scandir($directory),
                function (string $dirItem) {
                    return ! in_array($dirItem, ['.', '..'], true);
                }
            )
        );
        
        rmdir($directory);
    }

    /**
     * @group composer/composer#5237
     */
    public function testWillEscapeRegexParsingOfClassDefinitions()
    {
        self::assertSame(
            1,
            preg_match_all(
                '{^((?:final\s+)?(?:\s*))class\s+(\S+)}mi',
                file_get_contents((new \ReflectionClass(Installer::class))->getFileName())
            )
        );
    }
}
