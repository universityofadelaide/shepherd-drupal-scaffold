<?php

namespace UniversityOfAdelaide\ShepherdDrupalScaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem as ComposerFilesystem;
use Symfony\Component\Filesystem\Filesystem;

class Handler
{

    /**
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * Handler constructor.
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->filesystem = new Filesystem();
    }

    /**
    * Post update command event to execute the scaffolding.
    *
    * @param \Composer\Script\Event $event
    */
    public function onPostCmdEvent(\Composer\Script\Event $event)
    {
        $event->getIO()->write("Updating Shepherd scaffold files.");
        $this->updateShepherdScaffoldFiles();
        $event->getIO()->write("Creating necessary directories.");
        $this->createDirectories();
        $event->getIO()->write("Creating settings.php file if not present.");
        $this->populateSettingsFile();
        $event->getIO()->write("Removing write permissions on settings files.");
        $this->removeWritePermissions();
    }

    /**
     * Update the Shepherd scaffold files.
     */
    public function updateShepherdScaffoldFiles()
    {
        $packagePath = $this->getPackagePath();
        $projectPath = $this->getProjectPath();

        // Always copy and replace these files.
        $this->copyFiles(
            $packagePath,
            $projectPath,
            [
                'dsh',
                'RoboFileBase.php',
            ],
            true
        );

        // Only copy these files if they do not exist at the destination.
        $this->copyFiles(
            $packagePath,
            $projectPath,
            [
                'docker-compose.linux.yml',
                'docker-compose.osx.yml',
                'drush/config-delete.yml',
                'drush/config-ignore.yml',
                'dsh_bash',
                'phpcs.xml',
                'RoboFile.php',
                'docker/Dockerfile',
                'docker/xdebug.ini',
            ]
        );
    }

    /**
     * Ensure necessary directories exist.
     */
    public function createDirectories()
    {
        $root = $this->getDrupalRootPath();
        $dirs = [
            $root . '/modules',
            $root . '/profiles',
            $root . '/themes',
            'config-install',
            'config-export',
        ];

        // Required for unit testing.
        foreach ($dirs as $dir) {
            if (!$this->filesystem->exists($dir)) {
                $this->filesystem->mkdir($dir);
                $this->filesystem->touch($dir . '/.gitkeep');
            }
        }
    }

    /**
     * Create settings.php file and inject Shepherd-specific settings.
     *
     * Note: does nothing if the file already exists.
     */
    public function populateSettingsFile()
    {
        $root = $this->getDrupalRootPath();

        // Assume Drupal scaffold created the settings.php
        $this->filesystem->chmod($root . '/sites/default/settings.php', 0664);

        // If we haven't already written to settings.php.
        if (!(strpos(file_get_contents($root . '/sites/default/settings.php'), 'START SHEPHERD CONFIG') !== false)) {
          // Append Shepherd-specific environment variable settings to settings.php.
            file_put_contents(
                $root.'/sites/default/settings.php',
                $this->generateSettings(),
                FILE_APPEND
            );
        }
    }

    /**
     * Generates the "template" settings.php configuration.
     *
     * @return string
     *   PHP code.
     * @throws \Exception
     */
    public function generateSettings()
    {
      $settings = file_get_contents(__DIR__ . '/../fixtures/php/settings.php.txt');
      $hashSalt = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55)));
      return str_replace('<<<DEFAULT_HASH_SALT>>>', $hashSalt, $settings);
    }

    /**
     * Remove all write permissions on Drupal configuration files and folder.
     */
    public function removeWritePermissions()
    {
        $root = $this->getDrupalRootPath();
        $this->filesystem->chmod($root . '/sites/default/settings.php', 0444);
        $this->filesystem->chmod($root . '/sites/default', 0555);
    }

    /**
     * Copy files from origin to destination, optionally overwriting existing.
     *
     * @param bool $overwriteExisting
     *  If true, replace existing files. Defaults to false.
     */
    public function copyFiles($origin, $destination, $filenames, $overwriteExisting = false)
    {
        foreach ($filenames as $filename) {
            // Skip copying files that already exist at the destination.
            if (! $overwriteExisting && $this->filesystem->exists($destination . '/' . $filename)) {
                continue;
            }
            $this->filesystem->copy(
                $origin . '/' . $filename,
                $destination . '/' . $filename,
                true
            );
        }
    }

    /**
     * Get the path to the vendor directory.
     *
     * E.g. /home/user/code/project/vendor
     *
     * @return string
     */
    public function getVendorPath()
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
     *
     * @return string
     */
    public function getProjectPath()
    {
        $projectPath = dirname($this->getVendorPath());
        return $projectPath;
    }

    /**
     * Get the path to the package directory.
     *
     * E.g. /home/user/code/project/vendor/universityofadelaide/shepherd-drupal-scaffold
     *
     * @return string
     */
    public function getPackagePath()
    {
        $packagePath = $this->getVendorPath() . '/universityofadelaide/shepherd-drupal-scaffold';
        return $packagePath;
    }

    /**
     * Get the path to the Drupal root directory.
     *
     * E.g. /home/user/code/project/web
     *
     * @return string
     */
    public function getDrupalRootPath()
    {
        $drupalRootPath = $this->getProjectPath() . '/web';
        return $drupalRootPath;
    }
}
