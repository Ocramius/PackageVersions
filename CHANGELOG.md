# CHANGELOG


## 1.0.1 - 2016-02-01

This release fixes an issue related with composer updates to
already installed versions.
Using `composer require` within a package that already used
`ocramius/package-versions` caused the installation to be unable
to write the `PackageVersions\Versions` class to a file.

Total issues resolved: **6**

- [2: remove unused use statement](https://github.com/Ocramius/PackageVersions/pull/2)
- [3: Remove useless files from dist package](https://github.com/Ocramius/PackageVersions/pull/3)
- [5: failed to open stream: phar error: write operations disabled by the php.ini setting phar.readonly](https://github.com/Ocramius/PackageVersions/issues/5)
- [6: Fix/#5 use composer vendor dir](https://github.com/Ocramius/PackageVersions/pull/6)
- [7: Hotfix - #5 generate package versions also when in phar context](https://github.com/Ocramius/PackageVersions/pull/7)
- [8: Versions class should be ignored by VCS, as it is an install-time artifact](https://github.com/Ocramius/PackageVersions/pull/8)
