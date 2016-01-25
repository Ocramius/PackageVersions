# Package Versions

This utility provides very quick and easy access to installed composer dependency versions.

```php
$version = \PackageVersions\Versions::getVersion('ocramius/package-versions');

var_dump($versions); // 1.0.0@0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33
```

[![Build Status](https://travis-ci.org/Ocramius/PackageVersions.svg?branch=master)](https://travis-ci.org/Ocramius/PackageVersions)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Ocramius/PackageVersions/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Ocramius/PackageVersions/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/Ocramius/PackageVersions/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Ocramius/PackageVersions/?branch=master)
