<?php

namespace PackageVersionsTest;

use PHPUnit_Framework_TestCase;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * @coversNothing
 */
class E2EInstaller extends PHPUnit_Framework_TestCase
{
    private $tempGlobalComposerHome;
    private $tempLocalComposerHome;
    private $tempArtifact;

    public function setUp()
    {
        $this->tempGlobalComposerHome = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true) . '/global';
        $this->tempLocalComposerHome = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true) . '/local';
        $this->tempArtifact = sys_get_temp_dir() . '/' . uniqid('InstallerTest', true) . '/artifacts';
        mkdir($this->tempGlobalComposerHome, 0777, true);
        mkdir($this->tempLocalComposerHome, 0777, true);
        mkdir($this->tempArtifact, 0777, true);

        putenv('COMPOSER_HOME=' . $this->tempGlobalComposerHome);
    }

    public function tearDown()
    {
        $this->rmDir($this->tempGlobalComposerHome);
        $this->rmDir($this->tempLocalComposerHome);
        $this->rmDir($this->tempArtifact);
    }

    public function testGloballyInstalledPluginDoesNotGenerateVersionsForLocalProject()
    {
        $this->createPackageVersionsArtifact();

        file_put_contents($this->tempGlobalComposerHome . '/composer.json', json_encode([
            'name'         => 'package-versions/e2e-global',
            'require'      => [
                'ocramius/package-versions' => '1.0.0'
            ],
            'repositories' => [
                [
                    'packagist' => false,
                ],
                [
                    'type' => 'artifact',
                    'url' => $this->tempArtifact,
                ]
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->exec(__DIR__ . '/../../vendor/bin/composer global update');

        $this->createArtifact();
        file_put_contents($this->tempLocalComposerHome . '/composer.json', json_encode([
            'name'         => 'package-versions/e2e-local',
            'require'      => [
                'test/package' => '2.0.0'
            ],
            'repositories' => [
                [
                    'packagist' => false,
                ],
                [
                    'type' => 'artifact',
                    'url' => $this->tempArtifact,
                ]
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->execInDir('composer update', $this->tempLocalComposerHome);
        $this->assertFileNotExists(
            $this->tempLocalComposerHome . '/vendor/ocramius/package-versions/src/PackageVersions/Versions.php'
        );
    }

    public function testRemovingPluginDoesNotAttemptToGenerateVersions()
    {
        $this->createPackageVersionsArtifact();
        $this->createArtifact();

        file_put_contents($this->tempLocalComposerHome . '/composer.json', json_encode([
           'name'         => 'package-versions/e2e-local',
           'require'      => [
               'test/package' => '2.0.0',
               'ocramius/package-versions' => '1.0.0'
           ],
           'repositories' => [
               [
                   'packagist' => false,
               ],
               [
                   'type' => 'artifact',
                   'url' => $this->tempArtifact,
               ]
           ]
       ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->execInDir('composer update -vvv', $this->tempLocalComposerHome);
        $this->assertFileExists(
            $this->tempLocalComposerHome . '/vendor/ocramius/package-versions/src/PackageVersions/Versions.php'
        );

        $this->execInDir('composer remove ocramius/package-versions -vvv', $this->tempLocalComposerHome);
        $this->assertFileNotExists(
            $this->tempLocalComposerHome . '/vendor/ocramius/package-versions/src/PackageVersions/Versions.php'
        );
    }

    private function createPackageVersionsArtifact()
    {
        $zip = new ZipArchive();

        $zip->open($this->tempArtifact . '/ocramius-package-versions-1.0.0.zip', ZipArchive::CREATE);

        $files = array_filter(
            iterator_to_array(new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator(realpath(__DIR__ . '/../../'), RecursiveDirectoryIterator::SKIP_DOTS),
                    function (SplFileInfo $file) {
                        $filePath = substr($file->getRealPath(), strlen(realpath(__DIR__ . '/../../')) + 1);

                        if (substr($filePath, 0, 4) === '.git'
                            || substr($filePath, 0, 5) === '.idea'
                            || substr($filePath, 0, 6) === 'vendor'
                        ) {
                            return false;
                        }

                        return true;
                    }
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            )),
            function (SplFileInfo $file) {
                return !$file->isDir();
            }
        );

        array_walk(
            $files,
            function (SplFileInfo $file) use ($zip) {
                if ($file->getFilename() === 'composer.json') {
                    $contents = json_decode(file_get_contents($file->getRealPath()), true);
                    $contents['version'] = '1.0.0';

                    return $zip->addFromString('composer.json', json_encode($contents));
                }

                $zip->addFile(
                    $file->getRealPath(),
                    substr($file->getRealPath(), strlen(realpath(__DIR__ . '/../../')) + 1)
                );
            }
        );

        $zip->close();
    }

    private function createArtifact()
    {
        $zip = new ZipArchive();

        $zip->open($this->tempArtifact . '/test-package-2.0.0.zip', ZipArchive::CREATE);
        $zip->addFromString('composer.json', json_encode([
             'name'    => 'test/package',
             'version' => '2.0.0',
         ], JSON_PRETTY_PRINT)).
        $zip->close();
    }

    private function execInDir(string $command, string $dir) : array
    {
        $currentDir = getcwd();
        chdir($dir);
        $output = $this->exec($command);
        chdir($currentDir);
        return $output;
    }

    private function exec(string $command) : array
    {
        exec($command . ' 2> /dev/null', $output);
        return $output;
    }

    /**
     * @param string $directory
     *
     * @return void
     */
    private function rmDir(string $directory)
    {
        if (! is_dir($directory)) {
            unlink($directory);

            return;
        }

        array_map(
            function ($item) use ($directory) {
                $this->rmDir($directory . '/' . $item);
            },
            array_filter(
                scandir($directory),
                function (string $dirItem) {
                    return ! in_array($dirItem, ['.', '..'], true);
                }
            )
        );

        rmdir($directory);
    }
}
