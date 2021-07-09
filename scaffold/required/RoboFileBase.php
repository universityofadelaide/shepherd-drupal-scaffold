<?php

/**
 * @file
 * Contains \Robo\RoboFileBase.
 *
 * Implementation of class for Robo - http://robo.li/
 */

use Robo\Tasks;

/**
 * Class RoboFileBase.
 */
abstract class RoboFileBase extends Tasks {

  protected $drush_cmd;

  protected $drush_bin = "bin/drush";
  protected $composer_bin = "composer";

  /**
   * The path to the phpcs command.
   *
   * @var string
   */
  protected $phpcsCmd = 'bin/phpcs';

  /**
   * The path to the phpcbf command.
   *
   * @var string
   */
  protected $phpcbfCmd = 'bin/phpcbf';

  /**
   * The phpstan command.
   *
   * @var string
   */
  protected $phpstanCmd = 'php ./bin/phpstan analyze --no-progress';

  protected $php_enable_module_command = 'phpenmod -v ALL';
  protected $php_disable_module_command = 'phpdismod -v ALL';

  protected $application_root = "/code/web";
  protected $file_public_path = '/shared/public';
  protected $file_private_path = '/shared/private';
  protected $file_temp_path = '/shared/tmp';
  protected $services_yml = "web/sites/default/services.yml";

  protected $config = [];

  /**
   * The path to the Drupal config directories.
   */
  protected const CONFIG_DIRECTORY = '/code/config-export';
  protected const CONFIG_DIRECTORY_NEW = 'config_new';
  protected const CONFIG_DIRECTORY_OLD = 'config_old';


  /**
   * Initialize config variables and apply overrides.
   */
  public function __construct() {
    $this->drush_cmd = $this->drush_bin;

    // Read config from env vars.
    $environment_config = $this->readConfigFromEnv();
    $this->config = array_merge($this->config, $environment_config);
  }

  /**
   * Force projects to declare which install profile to use.
   *
   * I.e. return 'some_profile'.
   */
  protected function getDrupalProfile() {
    $profile = getenv('SHEPHERD_INSTALL_PROFILE');
    if (empty($profile)) {
      $this->say("Install profile environment variable is not set.\n");
      exit(1);
    }
    return $profile;
  }

  /**
   * Returns known configuration from environment variables.
   *
   * Runs during the constructor; be careful not to use Robo methods.
   */
  protected function readConfigFromEnv() {
    $config = [];

    // Site.
    $config['site']['title']            = getenv('SITE_TITLE');
    $config['site']['mail']             = getenv('SITE_MAIL');
    $config['site']['admin_email']      = getenv('SITE_ADMIN_EMAIL');
    $config['site']['admin_user']       = getenv('SITE_ADMIN_USERNAME');
    $config['site']['admin_password']   = getenv('SITE_ADMIN_PASSWORD');

    // Environment.
    $config['environment']['hash_salt']       = getenv('HASH_SALT');

    // Clean up NULL values and empty arrays.
    $array_clean = function (&$item) use (&$array_clean) {
      foreach ($item as $key => $value) {
        if (is_array($value)) {
          $array_clean($item[$key]);
        }
        if (empty($item[$key]) && $value !== '0') {
          unset($item[$key]);
        }
      }
    };

    $array_clean($config);

    return $config;
  }

  /**
   * Perform a full build on the project.
   */
  public function build(): void {
    $start = new DateTimeImmutable();
    $this->devXdebugDisable();
    $this->devComposerValidate();
    $this->buildMake();
    $this->ensureDirectories();
    $this->buildInstall();
    $this->configImport();
    $this->devCacheRebuild();
    $this->ensureDirectories();
    $this->devXdebugEnable();
    $this->say('Total build duration: ' . (new \DateTimeImmutable())->diff($start)->format('%im %Ss'));
  }

  /**
   * Perform a build for automated deployments.
   *
   * Don't install anything, just build the code base.
   */
  public function distributionBuild() {
    $this->devComposerValidate();
    $this->buildMake('--prefer-dist --no-suggest --no-dev --optimize-autoloader');
    $this->setSitePath();
  }

  /**
   * Validate composer files and installed dependencies with strict mode off.
   */
  public function devComposerValidate() {
    $this->taskComposerValidate()
      ->noCheckPublish()
      ->run()
      ->stopOnFail(TRUE);
  }

  /**
   * Run composer install to fetch the application code from dependencies.
   *
   * @param string $flags
   *   Additional flags to pass to the composer install command.
   */
  public function buildMake($flags = '') {
    $successful = $this->_exec(sprintf(
      '%s --no-progress --no-interaction %s install',
      $this->composer_bin,
      $flags)
    )->wasSuccessful();

    $this->checkFail($successful, "Composer install failed.");
  }

  /**
   * Set the owner and group of all files in the files dir to the web user.
   */
  public function ensureDirectories() {
    $publicDir = getenv('PUBLIC_DIR') ?: $this->file_public_path;
    $privateDir = getenv('PRIVATE_DIR') ?: $this->file_private_path;
    $tmpDir = getenv('TMP_DIR') ?: $this->file_temp_path;
    foreach ([$publicDir, $privateDir, $tmpDir] as $path) {
      $this->say("Ensuring all directories exist.");
      $this->_exec("mkdir -p $path");
      $this->say("Setting directory permissions.");
      $this->setPermissions($path, '0775');
    }
  }

  /**
   * Clean config and files, then install Drupal and module dependencies.
   */
  public function buildInstall() {
    // @TODO: When is this really used? Automated builds - can be random values.
    $successful = $this->_exec("$this->drush_cmd site:install " .
      $this->getDrupalProfile() .
      " install_configure_form.enable_update_status_module=NULL" .
      " install_configure_form.enable_update_status_emails=NULL" .
      " --yes" .
      " --account-mail=\"" . $this->config['site']['admin_email'] . "\"" .
      " --account-name=\"" . $this->config['site']['admin_user'] . "\"" .
      " --account-pass=\"" . $this->config['site']['admin_password'] . "\"" .
      " --site-name=\"" . $this->config['site']['title'] . "\"" .
      " --site-mail=\"" . $this->config['site']['mail'] . "\"")
      ->wasSuccessful();

    $this->checkFail($successful, 'drush site:install failed.');

    $this->devCacheRebuild();
  }

  /**
   * Set the RewriteBase value in .htaccess appropriate for the site.
   *
   * @TODO: Will OpenShift router deal with this for us?
   */
  public function setSitePath() {
    if (strlen($this->config['site']['path']) > 0) {
      $this->say("Setting site path.");
      $successful = $this->taskReplaceInFile("$this->application_root/.htaccess")
        ->from('# RewriteBase /drupal')
        ->to("\n  RewriteBase /" . ltrim($this->config['site']['path'], '/') . "\n")
        ->run();

      $this->checkFail($successful, "Couldn't update .htaccess file with path.");
    }
  }

  /**
   * Clean the application root in preparation for a new build.
   */
  public function buildClean() {
    $this->setPermissions("$this->application_root/sites/default", '0755');
    $this->_exec("rm -fR $this->application_root/core");
    $this->_exec("rm -fR $this->application_root/modules/contrib");
    $this->_exec("rm -fR $this->application_root/profiles/contrib");
    $this->_exec("rm -fR $this->application_root/themes/contrib");
    $this->_exec("rm -fR $this->application_root/sites/all");
    $this->_exec("rm -fR bin");
    $this->_exec("rm -fR vendor");
  }

  /**
   * Run all the drupal updates against a build.
   */
  public function buildApplyUpdates() {
    // Run the module updates.
    $successful = $this->_exec("$this->drush_cmd --yes updatedb")->wasSuccessful();
    $this->checkFail($successful, 'running drupal updates failed.');
  }

  /**
   * Perform cache clear in the app directory.
   */
  public function devCacheRebuild() {
    $successful = $this->_exec("$this->drush_cmd cache:rebuild")->wasSuccessful();

    $this->checkFail($successful, 'drush cache-rebuild failed.');
  }

  /**
   * Ask a couple of questions and then configure git.
   */
  public function devInit() {
    $this->say("Initial project setup. Adds user details to gitconfig.");
    $git_name  = $this->ask("Enter your Git name (e.g. Bob Rocks):");
    $git_email = $this->ask("Enter your Git email (e.g. bob@rocks.adelaide.edu.au):");
    $this->_exec("git config --global user.name \"$git_name\"");
    $this->_exec("git config --global user.email \"$git_email\"");

    // Automatically initialise git flow.
    $git_config = file_get_contents('.git/config');
    if (!strpos($git_config, '[gitflow')) {
      $this->taskWriteToFile(".git/config")
        ->append()
        ->text("\n[gitflow \"branch\"]\n" .
          "        master = master\n" .
          "        develop = develop\n" .
          "[gitflow \"prefix\"]\n" .
          "        feature = feature/\n" .
          "        release = release/\n" .
          "        hotfix = hotfix/\n" .
          "        support = support/\n" .
          "        versiontag = \n")
        ->run();
    }
  }

  /**
   * Install Adminer for database administration.
   */
  public function devInstallAdminer() {
    $this->taskFilesystemStack()
      ->remove("$this->application_root/adminer.php")
      ->run();

    $this->taskExec("wget -q -O adminer.php http://www.adminer.org/latest-mysql-en.php")
      ->dir($this->application_root)
      ->run();
  }

  /**
   * CLI debug enable.
   */
  public function devXdebugEnable() {
    // Only run this on environments configured with xdebug.
    if (getenv('XDEBUG_CONFIG')) {
      $this->_exec("sudo $this->php_enable_module_command -s cli xdebug");
    }
  }

  /**
   * CLI debug disable.
   */
  public function devXdebugDisable() {
    // Only run this on environments configured with xdebug.
    if (getenv('XDEBUG_CONFIG')) {
      $this->_exec("sudo $this->php_disable_module_command -s cli xdebug");
    }
  }

  /**
   * Imports Drupal configuration.
   */
  public function configImport() {
    $successful = $this->_exec(
      sprintf('%s config:import --yes --source=%s',
      $this->drush_cmd,
      static::CONFIG_DIRECTORY
    ))
    ->wasSuccessful();
    $this->checkFail($successful, 'Config import failed.');
  }

  /**
   * Exports Drupal configuration.
   *
   * @param string|null $destination
   *   An optional custom destination.
   */
  public function configExport(?string $destination = NULL): void {
    $this->_exec(sprintf(
      '%s config:export --yes --destination=%s',
      $this->drush_cmd,
      $destination ?? static::CONFIG_DIRECTORY
    ));
  }

  /**
   * Export the current configuration to a 'new' directory.
   */
  public function configExportNew(): void  {
    $this->configExport(static::CONFIG_DIRECTORY_OLD);
  }

  /**
   * Export the current configuration to an 'old' directory.
   */
  public function configExportOld(): void {
    $this->configExport(static::CONFIG_DIRECTORY_NEW);
  }

  /**
   * Turns on twig debug mode, autoreload on and caching off.
   */
  public function devTwigDebugEnable() {
    $this->taskReplaceInFile($this->services_yml)
      ->from('debug: false')
      ->to('debug: true')
      ->run();
    $this->taskReplaceInFile($this->services_yml)
      ->from('auto_reload: null')
      ->to('auto_reload: true')
      ->run();
    $this->taskReplaceInFile($this->services_yml)
      ->from('cache: true')
      ->to('cache: false')
      ->run();
    $this->devAggregateAssetsDisable();
    $this->say('Clearing Drupal cache...');
    $this->devCacheRebuild();
    $this->say('Done. Twig debugging has been enabled');
  }

  /**
   * Turn off twig debug mode, autoreload off and caching on.
   */
  public function devTwigDebugDisable() {
    $this->taskReplaceInFile($this->services_yml)
      ->from('debug: true')
      ->to('debug: false')
      ->run();
    $this->taskReplaceInFile($this->services_yml)
      ->from('auto_reload: true')
      ->to('auto_reload: null')
      ->run();
    $this->taskReplaceInFile($this->services_yml)
      ->from('c: false')
      ->to('cache: true')
      ->run();
    $this->say('Clearing Drupal cache...');
    $this->devCacheRebuild();
    $this->say('Done. Twig debugging has been disabled');
  }

  /**
   * Disable asset aggregation.
   */
  public function devAggregateAssetsDisable() {
    $this->taskExecStack()
      ->exec($this->drush_cmd . ' config:set system.performance js.preprocess 0 --yes')
      ->exec($this->drush_cmd . ' config:set system.performance css.preprocess 0 --yes')
      ->run();
    $this->devCacheRebuild();
    $this->say('Asset Aggregation is now disabled.');
  }

  /**
   * Enable asset aggregation.
   */
  public function devAggregateAssetsEnable() {
    $this->taskExecStack()
      ->exec($this->drush_cmd . ' config:set system.performance js.preprocess 1 --yes')
      ->exec($this->drush_cmd . ' config:set system.performance css.preprocess 1 --yes')
      ->run();
    $this->devCacheRebuild();
    $this->say('Asset Aggregation is now enabled.');
  }

  /**
   * Make config files write-able.
   *
   * @deprecated
   */
  public function devConfigWriteable() {
    $this->setPermissions("$this->application_root/sites/default/services.yml", '0664');
    $this->setPermissions("$this->application_root/sites/default/settings.php", '0664');
    $this->setPermissions("$this->application_root/sites/default/settings.local.php", '0664');
    $this->setPermissions("$this->application_root/sites/default", '0775');
  }

  /**
   * Make config files read only.
   *
   * @deprecated
   */
  public function devConfigReadOnly() {
    $this->setPermissions("$this->application_root/sites/default/services.yml", '0444');
    $this->setPermissions("$this->application_root/sites/default/settings.php", '0444');
    $this->setPermissions("$this->application_root/sites/default/settings.local.php", '0444');
    $this->setPermissions("$this->application_root/sites/default", '0555');
  }

  /**
   * Imports a database, updates the admin user password and applies updates.
   *
   * @param string $sql_file
   *   Path to sql file to import.
   */
  public function devImportDb($sql_file) {
    $start = new DateTimeImmutable();
    $this->_exec("$this->drush_cmd sql:drop --yes");
    $this->_exec("$this->drush_cmd sql:query --file=$sql_file");
    $this->devCacheRebuild();
    $this->buildApplyUpdates();

    $this->say('Duration: ' . (new \DateTimeImmutable())->diff($start)->format('%im %Ss'));
    $this->_exec("$this->drush_cmd upwd admin password");
    $this->say('Database imported, admin user password is : password');
  }

  /**
   * Exports a database and gzips the sql file.
   *
   * @param string $name
   *   Name of sql file to be exported.
   */
  public function devExportDb($name = 'dump') {
    $start = new DateTimeImmutable();
    $this->_exec("$this->drush_cmd sql:dump --gzip --result-file=$name.sql");
    $this->say('Duration: ' . (new \DateTimeImmutable())->diff($start)->format('%im %Ss'));
    $this->say("Database $name.sql.gz exported");
  }

  /**
   * Run coding standards checks for PHP files on the project.
   *
   * @param string $path
   *   An optional path to lint.
   */
  public function lintPhp($path = '') {
    $this->checkFail($this->_exec("$this->phpcsCmd $path")->wasSuccessful(), 'Code linting failed');
    $this->checkFail($this->_exec($this->phpstanCmd)->wasSuccessful(), 'Code analyzing failed');
  }

  /**
   * Fix coding standards violations for PHP files on the project.
   *
   * @param string $path
   *   An optional path to fix.
   */
  public function lintFix($path = '') {
    $this->_exec("$this->phpcbfCmd $path");
  }

  /**
   * Check if file exists and set permissions.
   *
   * @param string $file
   *   File to modify.
   * @param string $permission
   *   Permissions. E.g. '0644'.
   */
  protected function setPermissions($file, $permission) {
    if (file_exists($file)) {
      $this->_exec("chmod $permission $file");
    }
  }

  /**
   * Helper function to check whether a task has completed successfully.
   *
   * @param bool $successful
   *   Task ran successfully or not.
   * @param string $message
   *   Optional: A helpful message to print.
   */
  protected function checkFail($successful, $message = '') {
    if (!$successful) {
      $this->say('APP_ERROR: ' . $message);
      // Prevent any other tasks from executing.
      exit(1);
    }
  }

}
