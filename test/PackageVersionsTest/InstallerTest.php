<?php

namespace PackageVersionsTest;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use PackageVersions\Installer;
use PHPUnit_Framework_TestCase;

final class InstallerTest extends PHPUnit_Framework_TestCase
{
    public function testActivate()
    {
        $installer  = new Installer();
        /* @var $composer Composer|\PHPUnit_Framework_MockObject_MockObject */
        $composer   = $this->getMock(Composer::class);
        $dispatcher = $this->getMockBuilder(EventDispatcher::class)->disableOriginalConstructor()->getMock();
        /* @var $io IOInterface */
        $io         = $this->getMock(IOInterface::class);

        $composer->expects(self::any())->method('getEventDispatcher')->willReturn($dispatcher);
        $dispatcher->expects(self::once())->method('addSubscriber')->with($installer);

        $installer->activate($composer, $io);
    }
}