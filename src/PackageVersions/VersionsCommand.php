<?php

namespace PackageVersions;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CommandProvider
 */
class VersionsCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('versions')
            ->addArgument('package', InputArgument::OPTIONAL, 'Package name to display version for')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        if ($packageName = $input->getArgument('package')) {
            $output->writeln('Installed version:');
            $this->dumpPackageVersion($output, $packageName, Versions::getVersion($packageName));

            return 0;
        }

        $output->writeln('Installed versions:');
        $versionsSorted = \PackageVersions\Versions::VERSIONS;
        ksort($versionsSorted);

        foreach ($versionsSorted as $packageName => $version) {
            $this->dumpPackageVersion($output, $packageName, $version);
        }

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param string $packageName
     * @param string $version
     */
    private function dumpPackageVersion(OutputInterface $output, string $packageName, string $version)
    {
        list($version, $hash) = explode('@', $version);

        $output->writeln(sprintf(
            '<info>%s</info>: %s@%s',
            $packageName,
            $version,
            $hash
        ));
    }
}
