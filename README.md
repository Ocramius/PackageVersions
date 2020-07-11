# Package Versions

This utility provides quick and easy access to version information of composer dependencies.

This information is derived from the ```composer.lock``` file which is (re)generated during ```composer install``` or ```composer update```.

```php
$version = \PackageVersions\Versions::getVersion('ocramius/package-versions');

var_dump($version); // 1.0.0@0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33
```

[![Build Status](https://travis-ci.org/Ocramius/PackageVersions.svg?branch=master)](https://travis-ci.org/Ocramius/PackageVersions)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FOcramius%2FPackageVersions%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/Ocramius/PackageVersions/master)
[![Type Coverage](https://shepherd.dev/github/Ocramius/PackageVersions/coverage.svg)](https://shepherd.dev/github/Ocramius/PackageVersions)
[![Downloads](https://img.shields.io/packagist/dt/ocramius/package-versions.svg)](https://packagist.org/packages/ocramius/package-versions)
[![Packagist](https://img.shields.io/packagist/v/ocramius/package-versions.svg)](https://packagist.org/packages/ocramius/package-versions)
[![Dependencies](https://tidelift.com/badges/github/packagist/ocramius%2Fpackage-versions)](https://tidelift.com/subscription/pkg/packagist-ocramius%2Fpackage-versions?utm_source=packagist-ocramius%2Fpackage-versions&utm_medium=readme)

### Installation

```sh
composer require ocramius/package-versions
```

It is suggested that you use [an optimized composer autoloader](https://getcomposer.org/doc/06-config.md#optimize-autoloader) (to prevent autoload I/O when accessing the `PackageVersions\Versions` API) in your composer.json:
```
...
    "config": {
        "optimize-autoloader": true
    },
...
```

In case you manually generate your autoloader via the CLI use the `--optimize` flag:

```sh
composer dump-autoload --optimize
```

### Use-cases

This repository implements `PackageVersions\Versions::getVersion()` in such a way that no IO
happens when calling it, because the list of package versions is compiled during composer
installation.

This is especially useful when you want to generate assets/code/artifacts that are computed from
the current version of a certain dependency. Doing so at runtime by checking the installed
version of a package would be too expensive, and this package mitigates that.

## ocramius/package-versions for enterprise

Available as part of the Tidelift Subscription.

The maintainer of ocramius/package-versions and thousands of other packages are working with Tidelift to deliver commercial support and maintenance for the open source dependencies you use to build your applications. Save time, reduce risk, and improve code health, while paying the maintainers of the exact dependencies you use. [Learn more.](https://tidelift.com/subscription/pkg/packagist-ocramius-package-versions?utm_source=packagist-ocramius-package-versions&utm_medium=referral&utm_campaign=enterprise&utm_term=repo).

You can also contact the maintainer at ocramius@gmail.com for looking into issues related to this package
in your private projects.
