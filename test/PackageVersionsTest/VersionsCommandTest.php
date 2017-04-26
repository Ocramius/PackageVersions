<?php

namespace PackageVersionsTest;

use PackageVersions\Versions;
use PackageVersions\VersionsCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class VersionsCommandTest
 */
class VersionsCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testConfigure()
    {
        $command = new VersionsCommand();
        static::assertEquals('versions', $command->getName());
    }

    public function testExecute()
    {
        $application = new Application();
        $application->add(new VersionsCommand());

        $command = $application->find('versions');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));
        $display = $commandTester->getDisplay();

        foreach (Versions::VERSIONS as $packageName => $version) {
            list($version, ) = explode('@', $version);
            static::assertContains($packageName.': '.$version, $display);
        }
    }

    public function testExecuteWithPackage()
    {
        $application = new Application();
        $application->add(new VersionsCommand());

        $command = $application->find('versions');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), 'package' => 'ocramius/package-versions'));

        list($version, ) = explode('@', Versions::VERSIONS['ocramius/package-versions']);

        static::assertContains('ocramius/package-versions', $commandTester->getDisplay());
        static::assertContains($version, $commandTester->getDisplay());
    }
}
