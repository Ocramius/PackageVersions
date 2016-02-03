<?php

namespace PackageVersionsTest;

use Composer\Autoload\AutoloadGenerator;
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
     */
    protected function setUp()
    {
        parent::setUp();

        $this->installer       = new Installer();
        $this->io              = $this->getMock(IOInterface::class);
        $this->composer        = $this->getMock(Composer::class);
        $this->eventDispatcher = $this->getMockBuilder(EventDispatcher::class)->disableOriginalConstructor()->getMock();

        $this->composer->expects(self::any())->method('getEventDispatcher')->willReturn($this->eventDispatcher);
    }

    public function testActivate()
    {
        $this->eventDispatcher->expects(self::once())->method('addSubscriber')->with($this->installer);

        $this->installer->activate($this->composer, $this->io);
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
        $autoloadGenerator = $this->getMockBuilder(AutoloadGenerator::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->getMock(InstalledRepositoryInterface::class);
        $package           = $this->getMock(RootPackageInterface::class);

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

        $autoloadGenerator->expects(self::once())->method('dump');
        $repositoryManager->expects(self::any())->method('getLocalRepository')->willReturn($repository);

        $this->composer->expects(self::any())->method('getConfig')->willReturn($config);
        $this->composer->expects(self::any())->method('getLocker')->willReturn($locker);
        $this->composer->expects(self::any())->method('getAutoloadGenerator')->willReturn($autoloadGenerator);
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

    /**
     * @throws \OutOfBoundsException if a version cannot be located
     */
    public static function getVersionOnly(string $packageName) : string
    {
        list($version) = explode('@', self::getVersion($packageName));
        return $version;
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
     */
    public function testDumpsVersionsClassToSpecificLocation(RootPackageInterface $rootPackage, bool $inVendor)
    {
        $config            = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $locker            = $this->getMockBuilder(Locker::class)->disableOriginalConstructor()->getMock();
        $autoloadGenerator = $this->getMockBuilder(AutoloadGenerator::class)->disableOriginalConstructor()->getMock();
        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $installManager    = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $repository        = $this->getMock(InstalledRepositoryInterface::class);

        $vendorDir = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true) . '/vendor';

        mkdir($vendorDir, 0777, true);

        $expectedPath = $inVendor
            ? $vendorDir . '/ocramius/package-versions/src/PackageVersions'
            : realpath($vendorDir . '/..') . '/src/PackageVersions';

        mkdir($expectedPath, 0777, true);

        $locker
            ->expects(self::any())
            ->method('getLockData')
            ->willReturn([
                'packages' => [],
                'packages-dev' => [],
            ]);

        $autoloadGenerator->expects(self::once())->method('dump');
        $repositoryManager->expects(self::any())->method('getLocalRepository')->willReturn($repository);

        $this->composer->expects(self::any())->method('getConfig')->willReturn($config);
        $this->composer->expects(self::any())->method('getLocker')->willReturn($locker);
        $this->composer->expects(self::any())->method('getAutoloadGenerator')->willReturn($autoloadGenerator);
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
    }
}
