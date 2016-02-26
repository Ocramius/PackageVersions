# Package Versions

This utility provides very quick and easy access to installed composer dependency versions.

```php
$version = \PackageVersions\Versions::getVersion('ocramius/package-versions');

var_dump($version); // 1.0.0@0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33
```

[![Build Status](https://travis-ci.org/Ocramius/PackageVersions.svg?branch=master)](https://travis-ci.org/Ocramius/PackageVersions)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Ocramius/PackageVersions/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Ocramius/PackageVersions/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/Ocramius/PackageVersions/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Ocramius/PackageVersions/?branch=master)
[![Packagist](https://img.shields.io/packagist/v/ocramius/package-versions.svg)](https://packagist.org/packages/ocramius/package-versions)
[![Packagist Pre Release](https://img.shields.io/packagist/vpre/ocramius/package-versions.svg)](https://packagist.org/packages/ocramius/package-versions)

### Installation

```sh
composer require ocramius/package-versions
```

It is suggested that you re-dump the autoloader in order to prevent
autoload I/O when accessing the `PackageVersions\Versions` API:

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


