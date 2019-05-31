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
                'phpcs.xml',
                'RoboFile.php',
                'standalone-memcached.xml',
                'Dockerfile',
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
        return "\n/**\n * START SHEPHERD CONFIG\n */\n" .
            "\$databases['default']['default'] = array (\n" .
            "  'database' => getenv('DATABASE_NAME') ?: 'drupal',\n" .
            "  'username' => getenv('DATABASE_USER') ?: 'user',\n" .
            "  'password' => getenv('DATABASE_PASSWORD_FILE') ? file_get_contents(getenv('DATABASE_PASSWORD_FILE')) : 'password',\n" .
            "  'host' => getenv('DATABASE_HOST') ?: '127.0.0.1',\n" .
            "  'port' => getenv('DATABASE_PORT') ?: '3306',\n" .
            "  'driver' => getenv('DATABASE_DRIVER') ?: 'mysql',\n" .
            "  'prefix' => getenv('DATABASE_PREFIX') ?: '',\n" .
            "  'collation' => getenv('DATABASE_COLLATION') ?: 'utf8mb4_general_ci',\n" .
            "  'namespace' => getenv('DATABASE_NAMESPACE') ?: 'Drupal\\\\Core\\\\Database\\\\Driver\\\\mysql',\n" .
            ");\n" .
            "\$settings['file_private_path'] = getenv('PRIVATE_DIR') ?: '/shared/private';\n" .
            "\$settings['file_temporary_path'] = getenv('TMP_DIR') ?: '/shared/tmp';\n" .
            "\$settings['hash_salt'] = getenv('HASH_SALT') ?: '" . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55))) . "';\n" .
            "\$config_directories['sync'] = getenv('CONFIG_SYNC_DIRECTORY') ?: DRUPAL_ROOT . '/../config-export';\n" .
            "\$settings['shepherd_site_id'] = getenv('SHEPHERD_SITE_ID');\n" .
            "\$settings['shepherd_url'] = getenv('SHEPHERD_URL');\n" .
            "\$settings['shepherd_token'] = getenv('SHEPHERD_TOKEN_FILE') ? file_get_contents(getenv('SHEPHERD_TOKEN_FILE')) : getenv('SHEPHERD_TOKEN');\n\n" .
            "if (getenv('REDIS_ENABLED')) {\n" .
            "  \$settings['redis.connection']['interface'] = 'PhpRedis';\n" .
            "  \$settings['redis.connection']['host'] = getenv('REDIS_HOST') ?: '127.0.0.1';\n" .
            "  // Always set the fast backend for bootstrap, discover and config, otherwise\n" .
            "  // this gets lost when redis is enabled.\n" .
            "  \$settings['cache']['bins']['bootstrap'] = 'cache.backend.chainedfast';\n" .
            "  \$settings['cache']['bins']['discovery'] = 'cache.backend.chainedfast';\n" .
            "  \$settings['cache']['bins']['config'] = 'cache.backend.chainedfast';\n\n" .
            "  \$settings['cache_prefix']['default'] = getenv('REDIS_PREFIX') ?: 'mysite_';\n" .
            "  // If we're not installing, include the redis services.\n" .
            "  if (!isset(\$GLOBALS['install_state'])) {\n" .
            "    \$settings['cache']['default'] = 'cache.backend.redis';\n\n" .
            "    \$settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';\n" .
            "  }\n" .
            "}\n" .
            "if (getenv('MEMCACHE_ENABLED')) {\n" .
            "  \$settings['memcache']['servers'] = [getenv('MEMCACHE_HOST') . ':11211' => 'default'] ?: ['127.0.0.1:11211' => 'default'];\n" .
            "  \$settings['memcache']['bins'] = ['default' => 'default'];\n" .
            "  \$settings['memcache']['key_prefix'] = '';\n" .
            "  // If we're not installing, include the memcache services.\n" .
            "  if (!isset(\$GLOBALS['install_state'])) {\n" .
            "    \$settings['cache']['default'] = 'cache.backend.memcache';\n" .
            "  }\n" .
            "}\n" .
            "if (getenv('SHEPHERD_SECRET_PATH')) {\n" .
            "  \$settings['shepherd_secrets'] = []; \n" .
            "  // Glob the secret path for secrets, that match pattern \n" .
            "  foreach ( glob( rtrim(getenv('SHEPHERD_SECRET_PATH'),DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'SHEPHERD_*') as \$secret) {\n" .
            "    \$settings['shepherd_secrets'][pathinfo(\$secret)['filename']] = file_get_contents(\$secret);\n" .
            "  }\n" .
            "}\n" .
            "if (getenv('SHEPHERD_REVERSE_PROXY')) {\n" .
            "  \$settings['reverse_proxy'] = TRUE; \n" .
            "  \$settings['reverse_proxy_header'] = getenv('SHEPHERD_REVERSE_PROXY_HEADER') ?: 'X_CLUSTER_CLIENT_IP';\n" .
            "  \$settings['reverse_proxy_addresses'] = !empty(getenv('SHEPHERD_REVERSE_PROXY_ADDRESSES')) ? explode(',', getenv('SHEPHERD_REVERSE_PROXY_ADDRESSES')) : [];\n" .
            "  \$settings['reverse_proxy_proto_header'] = getenv('SHEPHERD_REVERSE_PROXY_PROTO_HEADER') ?: 'X_FORWARDED_PROTO';\n" .
            "  \$settings['reverse_proxy_host_header'] = getenv('SHEPHERD_REVERSE_PROXY_HOST_HEADER') ?: 'X_FORWARDED_HOST';\n" .
            "  \$settings['reverse_proxy_port_header'] = getenv('SHEPHERD_REVERSE_PROXY_PORT_HEADER') ?: 'X_FORWARDED_PORT';\n" .
            "  \$settings['reverse_proxy_forwarded_header'] = getenv('SHEPHERD_REVERSE_PROXY_FORWARDED_HEADER') ?: 'FORWARDED';\n" .
            "}\n" .
            "/**\n * END SHEPHERD CONFIG\n */\n" .
            "\n" .
            "/**\n * START LOCAL CONFIG\n */\n" .
            "if (file_exists(__DIR__ . '/settings.local.php')) {\n" .
            "  include __DIR__ . '/settings.local.php';\n" .
            "}\n" .
            "/**\n * END LOCAL CONFIG\n */\n";
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
