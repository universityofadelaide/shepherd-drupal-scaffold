<?php

/**
 * @file
 * Contains \Robo\RoboFileBase.
 *
 * Implementation of class for Robo - http://robo.li/
 */

/**
 * Class RoboFileBase.
 */
abstract class RoboFileBase extends \Robo\Tasks {

  protected $drush_cmd;
  protected $local_user;

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

  protected $web_server_user = 'www-data';

  protected $application_root = "/code/web";
  protected $file_public_path = '/shared/public';
  protected $file_private_path = '/shared/private';
  protected $file_temporary_path = '/shared/tmp';
  protected $services_yml = "web/sites/default/services.yml";
  protected $settings_php = "web/sites/default/settings.php";

  protected $config = [];

  protected $config_new_directory = 'config_new';
  protected $config_old_directory = 'config_old';

  /**
   * The path to the config dir.
   *
   * @var string
   */
  protected $configDir = '/code/config-export';

  /**
   * The path to the config install dir.
   *
   * @var string
   */
  protected $configInstallDir = '/code/config-install';

  /**
   * The path to the config delete list.
   *
   * @var string
   */
  protected $configDeleteList = '/code/drush/config-delete.yml';

  /**
   * The path to the config delete list.
   *
   * @var string
   */
  protected $configIgnoreList = '/code/drush/config-ignore.yml';

  /**
   * Initialize config variables and apply overrides.
   */
  public function __construct() {
    $this->drush_cmd = $this->drush_bin;
    $this->local_user = $this->getLocalUser();

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
  public function build() {
    $start = new DateTime();
    $this->devXdebugDisable();
    $this->devComposerValidate();
    $this->buildMake();
    $this->buildSetFilesOwner();
    $this->buildInstall();
    $this->configImportPlus();
    $this->devCacheRebuild();
    $this->buildSetFilesOwner();
    $this->devXdebugEnable();
    $this->say('Total build duration: ' . date_diff(new DateTime(), $start)->format('%im %Ss'));
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
    $successful = $this->_exec("$this->composer_bin --no-progress --no-interaction $flags install")->wasSuccessful();

    $this->checkFail($successful, "Composer install failed.");
  }

  /**
   * Set the owner and group of all files in the files dir to the web user.
   */
  public function buildSetFilesOwner() {
    foreach ([$this->file_public_path, $this->file_private_path, $this->file_temporary_path] as $path) {
      $this->say("Ensuring all directories exist.");
      $this->_exec("mkdir -p $path");
      $this->say("Setting files directory owner.");
      $this->_exec("chown $this->web_server_user:$this->local_user -R $path");
      $this->say("Setting directory permissions.");
      $this->setPermissions($path, '0775');
    }
  }

  /**
   * Clean config and files, then install Drupal and module dependencies.
   */
  public function buildInstall() {
    $this->devConfigWriteable();

    // @TODO: When is this really used? Automated builds - can be random values.
    $successful = $this->_exec("$this->drush_cmd site-install " .
      $this->getDrupalProfile() .
      " install_configure_form.enable_update_status_module=NULL" .
      " install_configure_form.enable_update_status_emails=NULL" .
      " -y" .
      " --account-mail=\"" . $this->config['site']['admin_email'] . "\"" .
      " --account-name=\"" . $this->config['site']['admin_user'] . "\"" .
      " --account-pass=\"" . $this->config['site']['admin_password'] . "\"" .
      " --site-name=\"" . $this->config['site']['title'] . "\"" .
      " --site-mail=\"" . $this->config['site']['mail'] . "\"")
      ->wasSuccessful();

    // Re-set settings.php permissions.
    $this->devConfigReadOnly();

    $this->checkFail($successful, 'drush site-install failed.');

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
    $successful = $this->_exec("$this->drush_cmd -y updatedb")->wasSuccessful();
    $this->checkFail($successful, 'running drupal updates failed.');
  }

  /**
   * Perform cache clear in the app directory.
   */
  public function devCacheRebuild() {
    $successful = $this->_exec("$this->drush_cmd cr")->wasSuccessful();

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
   * Export the current configuration to an 'old' directory.
   */
  public function configExportOld() {
    $this->configExport($this->config_old_directory);
  }

  /**
   * Export the current configuration to a 'new' directory.
   */
  public function configExportNew() {
    $this->configExport($this->config_new_directory);
  }

  /**
   * Export config to a supplied directory.
   *
   * @param string $destination
   *   The folder within the application root.
   */
  protected function configExport($destination = NULL) {
    $this->yell('This command is deprecated, you should use config:export-plus instead', 40, 'red');
    if ($destination) {
      $this->_exec("$this->drush_cmd -y cex --destination=" . $destination);
      $this->_exec("sed -i '/^uuid:.*$/d; /^_core:$/, /^.*default_config_hash:.*$/d' $this->application_root/$destination/*.yml");
    }
  }

  /**
   * Display files changed between 'config_old' and 'config_new' directories.
   *
   * @param array $opts
   *   Specify whether to show the diff output or just list them.
   *
   * @return array
   *   Diff output as an array of strings.
   */
  public function configChanges($opts = ['show|s' => FALSE]) {
    $this->yell('This command is deprecated, you should use config:export-plus and config:import-plus instead', 40, 'red');
    $output_style = '-qbr';
    $config_old_path = $this->application_root . '/' . $this->config_old_directory;
    $config_new_path = $this->application_root . '/' . $this->config_new_directory;

    if (isset($opts['show']) && $opts['show']) {
      $output_style = '-ubr';
    }

    $results = $this->taskExec("diff -N -I \"   - 'file:.*\" $output_style $config_old_path $config_new_path")
      ->run()
      ->getMessage();

    $results_array = explode("\n", $results);

    return $results_array;
  }

  /**
   * Synchronise active config to the install profile or specified path.
   *
   * Synchronise the differences from the configured 'config_new' and
   * 'config_old' directories into the install profile or a specific path.
   *
   * @param array $path
   *   If the sync is to update an entity instead of a profile, supple a path.
   */
  public function configSync($path = NULL) {
    $this->yell('This command is deprecated, you should use config:export-plus and config:import-plus instead', 40, 'red');
    $config_sync_already_run = FALSE;
    $output_path = $this->application_root . '/profiles/' . $this->getDrupalProfile() . '/config/install';
    $config_new_path = $this->application_root . '/' . $this->config_new_directory;

    // If a path is passed in, use it to override the destination.
    if (!empty($path) && is_dir($path)) {
      $output_path = $path;
    }

    $results_array = $this->configChanges();

    $tasks = $this->taskFilesystemStack();

    foreach ($results_array as $line) {
      // Handle/remove blank lines.
      $line = trim($line);
      if (empty($line)) {
        continue;
      }

      // Never sync the extension file, it breaks things.
      if (stristr($line, 'core.extension.yml')) {
        continue;
      }

      // Break up the line into fields and put the parts in their place.
      $parts = explode(' ', $line);
      $config_new_file = $parts[3];
      $output_file_path = $output_path .
        preg_replace("/^" . str_replace('/', '\/', $config_new_path) ."/", '', $config_new_file);

      // If the source doesn't exist, we're removing it from the
      // destination in the profile.
      if (!file_exists($config_new_file)) {
        if (file_exists($output_file_path)) {
          $tasks->remove($output_file_path);
        }
        else {
          $config_sync_already_run = TRUE;
        }
      }
      else {
        $tasks->copy($config_new_file, $output_file_path);
      }
    }

    if ($config_sync_already_run) {
      $this->say("Config sync already run?");
    }

    $tasks->run();
  }

  /**
   * Imports configuration using advanced drush commands.
   *
   * Commands provided by previousnext/drush_cmi_tools.
   *
   * @see https://github.com/previousnext/drush_cmi_tools
   */
  public function configImportPlus() {
    $this->_exec("$this->drush_cmd cimy -y --source=$this->configDir --install=$this->configInstallDir --delete-list=$this->configDeleteList");
  }

  /**
   * Exports configuration using advanced drush commands.
   *
   * Commands provided by previousnext/drush_cmi_tools.
   *
   * @see https://github.com/previousnext/drush_cmi_tools
   */
  public function configExportPlus() {
    $this->_exec("$this->drush_cmd cexy -y --destination=$this->configDir --ignore-list=$this->configIgnoreList");
  }

  /**
   * Turns on twig debug mode, autoreload on and caching off.
   */
  public function devTwigDebugEnable() {
    $this->devConfigWriteable();
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
    $this->devConfigReadOnly();
    $this->say('Clearing Drupal cache...');
    $this->devCacheRebuild();
    $this->say('Done. Twig debugging has been enabled');
  }

  /**
   * Turn off twig debug mode, autoreload off and caching on.
   */
  public function devTwigDebugDisable() {
    $this->devConfigWriteable();
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
    $this->devConfigReadOnly();
    $this->say('Clearing Drupal cache...');
    $this->devCacheRebuild();
    $this->say('Done. Twig debugging has been disabled');
  }

  /**
   * Disable asset aggregation.
   */
  public function devAggregateAssetsDisable() {
    $this->taskExecStack()
      ->exec($this->drush_cmd . ' cset system.performance js.preprocess 0 -y')
      ->exec($this->drush_cmd . ' cset system.performance css.preprocess 0 -y')
      ->run();
    $this->devCacheRebuild();
    $this->say('Asset Aggregation is now disabled.');
  }

  /**
   * Enable asset aggregation.
   */
  public function devAggregateAssetsEnable() {
    $this->taskExecStack()
      ->exec($this->drush_cmd . ' cset system.performance js.preprocess 1 -y')
      ->exec($this->drush_cmd . ' cset system.performance css.preprocess 1 -y')
      ->run();
    $this->devCacheRebuild();
    $this->say('Asset Aggregation is now enabled.');
  }

  /**
   * Make config files write-able.
   */
  public function devConfigWriteable() {
    $this->setPermissions("$this->application_root/sites/default/services.yml", '0664');
    $this->setPermissions("$this->application_root/sites/default/settings.php", '0664');
    $this->setPermissions("$this->application_root/sites/default/settings.local.php", '0664');
    $this->setPermissions("$this->application_root/sites/default", '0775');
  }

  /**
   * Make config files read only.
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
    $start = new DateTime();
    $this->_exec("$this->drush_cmd -y sql-drop");
    $this->_exec("$this->drush_cmd sqlq --file=$sql_file");
    $this->_exec("$this->drush_cmd cr");
    $this->_exec("$this->drush_cmd upwd admin --password=password");
    $this->_exec("$this->drush_cmd updb --entity-updates -y");
    $this->say('Duration: ' . date_diff(new DateTime(), $start)->format('%im %Ss'));
    $this->say('Database imported, admin user password is : password');
  }

  /**
   * Exports a database and gzips the sql file.
   *
   * @param string $name
   *   Name of sql file to be exported.
   */
  public function devExportDb($name = 'dump') {
    $start = new DateTime();
    $this->_exec("$this->drush_cmd sql-dump --gzip --result-file=$name.sql");
    $this->say("Duration: " . date_diff(new DateTime(), $start)->format('%im %Ss'));
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
   * Return the name of the local user.
   *
   * @return string
   *   Returns the current user.
   */
  protected function getLocalUser() {
    $user = posix_getpwuid(posix_getuid());
    return $user['name'];
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
