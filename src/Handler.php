<?php

namespace UniversityOfAdelaide\ShepherdDrupalScaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;

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
     * @var \Composer\Util\Filesystem
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
        $this->createSettingsFile();
        $event->getIO()->write("Creating services.yml file if not present.");
        $this->createServicesFile();
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
                'docker-compose.yml',
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
                'RoboFile.php',
            ]
        );
    }

    /**
     * Ensure necessary directories exist.
     */
    public function createDirectories()
    {
        $root = $this->getDrupalRoot();
        $dirs = [
            'modules',
            'profiles',
            'themes',
        ];

        // Required for unit testing.
        foreach ($dirs as $dir) {
            if (!$this->filesystem->exists($root . '/'. $dir)) {
                $this->filesystem->mkdir($root . '/'. $dir);
                $this->filesystem->touch($root . '/'. $dir . '/.gitkeep');
            }
        }
    }

    /**
     * Create settings.php file and inject Shepherd-specific settings.
     *
     * Note: does nothing if the file already exists.
     */
    public function createSettingsFile()
    {
        $root = $this->getDrupalRoot();

        // If the settings.php is not present, and the default version is...
        if (!$this->filesystem->exists($root . '/sites/default/settings.php') && $this->filesystem->exists($root . '/sites/default/default.settings.php')) {
            $this->filesystem->copy($root . '/sites/default/default.settings.php', $root . '/sites/default/settings.php');
            $this->filesystem->chmod($root . '/sites/default/settings.php', 0666);

            $shepherdSettings = "\n/**\n * START SHEPHERD CONFIG\n */\n" .
                "\$databases['default']['default'] = array (\n" .
                "  'database' => getenv('DATABASE_NAME'),\n" .
                "  'username' => getenv('DATABASE_USER'),\n" .
                "  'password' => getenv('DATABASE_PASSWORD_FILE') ? file_get_contents(getenv('DATABASE_PASSWORD_FILE')) : getenv('DATABASE_PASSWORD'),\n" .
                "  'host' => getenv('DATABASE_HOST'),\n" .
                "  'port' => getenv('DATABASE_PORT') ?: '3306',\n" .
                "  'driver' => getenv('DATABASE_DRIVER') ?: 'mysql',\n" .
                "  'prefix' => getenv('DATABASE_PREFIX') ?: '',\n" .
                "  'collation' => getenv('DATABASE_COLLATION') ?: 'utf8mb4_general_ci',\n" .
                "  'namespace' => getenv('DATABASE_NAMESPACE') ?: 'Drupal\\\\Core\\\\Database\\\\Driver\\\\mysql',\n" .
                ");\n" .
                "\$settings['file_private_path'] = getenv('PRIVATE_DIR');\n" .
                "\$settings['hash_salt'] = getenv('HASH_SALT') ?: '" . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55))) . "';\n" .
                "\$config_directories['sync'] = getenv('CONFIG_SYNC_DIRECTORY') ?: 'sites/default/files/config_" . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55))) . "/sync';\n" .
                "if (! is_dir(\$app_root . '/' . \$config_directories['sync'])) mkdir(\$app_root . '/' . \$config_directories['sync'], 0777, true);\n" .
                "\$settings['shepherd_site_id'] = getenv('SHEPHERD_SITE_ID');\n" .
                "\$settings['shepherd_url'] = getenv('SHEPHERD_URL');\n" .
                "\$settings['shepherd_token'] = getenv('SHEPHERD_TOKEN_FILE') ? file_get_contents(getenv('SHEPHERD_TOKEN_FILE')) : getenv('SHEPHERD_TOKEN');\n" .
                "/**\n * END SHEPHERD CONFIG\n */\n" .
                "\n" .
                "/**\n * START LOCAL CONFIG\n */\n" .
                "if (file_exists(__DIR__ . '/settings.local.php')) {\n" .
                "  include __DIR__ . '/settings.local.php';\n" .
                "}\n" .
                "/**\n * END LOCAL CONFIG\n */\n\n"
            ;

            // Append Shepherd-specific environment variable settings to settings.php.
            file_put_contents(
                $root . '/sites/default/settings.php',
                $shepherdSettings,
                FILE_APPEND
            );
        }
    }

    /**
     * Create services.yml file if not present.
     */
    public function createServicesFile()
    {
        $root = $this->getDrupalRoot();

        if (!$this->filesystem->exists($root . '/sites/default/services.yml') && $this->filesystem->exists($root . '/sites/default/default.services.yml')) {
            $this->filesystem->copy($root . '/sites/default/default.services.yml', $root . '/sites/default/services.yml');
            $this->filesystem->chmod($root . '/sites/default/services.yml', 0666);
        }
    }

    /**
     * Copy files from origin to destination, optionally overwritting existing.
     *
     * @param bool $overwriteExisting
     *  If true, replace existing files. Defaults to false.
     */
    public function copyFiles($origin, $destination, $filenames, $overwriteExisting = false)
    {
        foreach ($filenames as $filename) {
            // Skip copying files that already exist at the destination.
            if (! $overwriteExisting && file_exists($destination . '/' . $filename)) {
                continue;
            }
            copy(
                $origin . '/' . $filename,
                $destination . '/' . $filename
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
        $config = $this->composer->getConfig();
        $this->filesystem->ensureDirectoryExists($config->get('vendor-dir'));
        $vendorPath = $this->filesystem->normalizePath(realpath($config->get('vendor-dir')));

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
