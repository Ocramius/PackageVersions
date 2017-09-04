<?php

namespace PackageVersions;

use Throwable;

class NotInstalledException extends \RuntimeException
{
    const MESSAGE = "Folder of ocramius/package-versions not found, it seems that it's not installed";

    public function __construct($message = self::MESSAGE, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
