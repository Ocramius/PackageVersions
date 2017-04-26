<?php

namespace PackageVersionsTest;

use PackageVersions\CommandProvider;
use PackageVersions\VersionsCommand;

/**
 * Class CommandProviderTest
 */
class CommandProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetCommands()
    {
        $commands = (new CommandProvider())->getCommands();

        static::assertCount(1, $commands);
        static::assertInstanceOf(VersionsCommand::class, $commands[0]);
    }
}
