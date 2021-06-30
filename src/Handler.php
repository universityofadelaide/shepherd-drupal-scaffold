<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem as ComposerFilesystem;
use Symfony\Component\Filesystem\Filesystem;
use UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\CopyFile;
use UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\CreateDirectory;

class Handler
{
    protected Composer $composer;
    protected IOInterface $io;
    protected Filesystem $filesystem;

    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->filesystem = new Filesystem();
    }

    /**
     * Post update command event to execute the scaffolding.
     */
    public function onPostCmdEvent(Event $event): void
    {
        $event->getIO()->write('Updating Shepherd scaffold files.');
        $this->updateShepherdScaffoldFiles();
        $event->getIO()->write('Adding scaffold files to .gitignore');
        $this->updateGitIgnoreScaffoldFiles();
        $event->getIO()->write('Creating necessary directories.');
        $this->createDirectories();
        $event->getIO()->write('Creating settings.php file if not present.');
        $this->populateSettingsFile();
        $event->getIO()->write('Removing write permissions on settings files.');
        $this->removeWritePermissions();
    }

    /**
     * Update the Shepherd scaffold files.
     */
    public function updateShepherdScaffoldFiles(): void
    {
        $scaffoldPath = $this->getScaffoldDirectory();
        foreach ($this->getFileTasks($scaffoldPath) as $task) {
            $task->execute($this->filesystem, $this->getProjectPath());
        }
    }

    /**
     * Adds scaffold files to .gitignore.
     */
    public function updateGitIgnoreScaffoldFiles(): void
    {
        // Only continue if there is a .gitignore file.
        $gitIgnorePath = $this->getProjectPath() . '/.gitignore';
        if (!$this->filesystem->exists($gitIgnorePath)) {
            return;
        }

        $gitIgnore = file_get_contents($gitIgnorePath);

        // Get list of file paths which need to be added to .gitignore.
        $paths = array_filter(
            array_map(fn (CopyFile $task) => $task->getFilename(), $this->getFileTasks($this->getScaffoldDirectory())),
            function (string $fileName) use ($gitIgnore): bool {
                return false === strpos($gitIgnore, $fileName);
            }
        );

        $append = '';
        foreach ($paths as $path) {
            $append .= sprintf("%s\n", $path);
        }

        if (!empty($append)) {
            file_put_contents($gitIgnorePath, "\n" . $append, \FILE_APPEND);
        }
    }

    /**
     * Ensure necessary directories exist.
     */
    public function createDirectories(): void
    {
        $root = $this->getDrupalRootPath();
        foreach ($this->getCreateDirectoryTasks($root) as $task) {
            $task->execute($this->filesystem);
        }
    }

    /**
     * Create settings.php file and inject Shepherd-specific settings.
     *
     * Note: does nothing if the file already exists.
     */
    public function populateSettingsFile(): void
    {
        $root = $this->getDrupalRootPath();

        // Assume Drupal scaffold created the settings.php
//        $this->filesystem->chmod($root . '/sites/default/settings.php', 0664);

        // If we haven't already written to settings.php.
        if (!(false !== strpos(file_get_contents($root . '/sites/default/settings.php'), 'START SHEPHERD CONFIG'))) {
            // Append Shepherd-specific environment variable settings to settings.php.
            file_put_contents(
                $root . '/sites/default/settings.php',
                $this->generateSettings(),
                FILE_APPEND
            );
        }
    }

    /**
     * Generates the "template" settings.php configuration.
     *
     * @return string
     *   Contents of the settings.php file.
     */
    public function generateSettings(): string
    {
        $settings = file_get_contents(__DIR__ . '/../fixtures/php/settings.php.txt');
        $hashSalt = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55)));

        return str_replace('<<<DEFAULT_HASH_SALT>>>', $hashSalt, $settings);
    }

    /**
     * Remove all write permissions on Drupal configuration files and folder.
     */
    public function removeWritePermissions(): void
    {
        $root = $this->getDrupalRootPath();
//        $this->filesystem->chmod($root . '/sites/default/settings.php', 0444);
//        $this->filesystem->chmod($root . '/sites/default', 0555);
    }

    /**
     * Get the path to the vendor directory.
     *
     * E.g. /home/user/code/project/vendor
     */
    public function getVendorPath(): string
    {
        // Load ComposerFilesystem to get access to path normalisation.
        $composerFilesystem = new ComposerFilesystem();

        $config = $this->composer->getConfig();
        $composerFilesystem->ensureDirectoryExists($config->get('vendor-dir'));
        $vendorPath = $composerFilesystem->normalizePath(realpath($config->get('vendor-dir')));

        return $vendorPath;
    }

    /**
     * Get the path to the project directory.
     *
     * E.g. /home/user/code/project
     */
    public function getProjectPath(): string
    {
        return dirname($this->getVendorPath());
    }

    /**
     * Get the path to the Drupal root directory.
     *
     * E.g. /home/user/code/project/web
     */
    public function getDrupalRootPath(): string
    {
        return $this->getProjectPath() . '/web';
    }

    /**
     * Path to scaffold files.
     */
    public function getScaffoldDirectory(): string
    {
        return $this->getVendorPath() . '/universityofadelaide/shepherd-drupal-scaffold/scaffold';
    }

    /**
     * @return \UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\CopyFile[]
     */
    protected function getFileTasks(string $scaffoldPath): array
    {
        return array_map(fn ($args): CopyFile => new CopyFile(...$args), [
            // Always copy and replace these files.
            [$scaffoldPath . '/required', 'dsh', true],
            [$scaffoldPath . '/required', 'RoboFileBase.php', true],

            // Only copy these files if they do not exist at the destination.
            [$scaffoldPath . '/optional', 'docker-compose.linux.yml'],
            [$scaffoldPath . '/optional', 'docker-compose.osx.yml'],
            [$scaffoldPath . '/optional', 'dsh_bash'],
            [$scaffoldPath . '/optional', 'phpcs.xml'],
            [$scaffoldPath . '/optional', 'RoboFile.php'],
            [$scaffoldPath . '/optional', 'docker/Dockerfile'],
            [$scaffoldPath . '/optional', 'docker/xdebug.ini'],
            [$scaffoldPath . '/optional', 'docker/php_custom.ini'],
        ]);
    }

    /**
     * @return \UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\CreateDirectory[]
     */
    protected function getCreateDirectoryTasks(string $root): array
    {
        return array_map(fn (string $path): CreateDirectory => new CreateDirectory($path), [
            $root . '/modules',
            $root . '/profiles',
            $root . '/themes',
            'config-install',
            'config-export',
        ]);
    }
}
