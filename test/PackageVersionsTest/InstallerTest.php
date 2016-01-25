<?php

namespace PackageVersionsTest;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
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
}
