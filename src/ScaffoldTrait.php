<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem as ComposerFilesystem;
use Symfony\Component\Filesystem\Filesystem;

trait ScaffoldTrait
{
    protected Composer $composer;
    protected IOInterface $io;
    protected Filesystem $filesystem;

    public function create(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->filesystem = new Filesystem();
    }

    /**
     * Get the path to the Drupal root directory.
     *
     * E.g. /home/user/code/project/web
     */
    protected function getDrupalRootPath(): string
    {
        return $this->getProjectPath() . '/web';
    }

    /**
     * Get the path to the project directory.
     *
     * E.g. /home/user/code/project
     */
    protected function getProjectPath(): string
    {
        return dirname($this->getVendorPath());
    }

    /**
     * Path to scaffold files.
     */
    protected function getScaffoldDirectory(): string
    {
        return $this->getVendorPath() . '/universityofadelaide/shepherd-drupal-scaffold/scaffold';
    }

    /**
     * Get the path to the vendor directory.
     *
     * E.g. /home/user/code/project/vendor
     */
    protected function getVendorPath(): string
    {
        // Load ComposerFilesystem to get access to path normalisation.
        $composerFilesystem = new ComposerFilesystem();

        $config = $this->composer->getConfig();
        $composerFilesystem->ensureDirectoryExists($config->get('vendor-dir'));

        return $composerFilesystem->normalizePath(realpath($config->get('vendor-dir')));
    }
}
