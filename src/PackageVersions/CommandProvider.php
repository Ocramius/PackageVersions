<?php

namespace PackageVersions;

use Composer\Plugin\Capability\CommandProvider as CommandProviderInterface;
use Composer\Plugin\Capability\Composer;

/**
 * Class CommandProvider
 */
class CommandProvider implements CommandProviderInterface
{
    public function getCommands()
    {
        return [
            new VersionsCommand()
        ];
    }
}
