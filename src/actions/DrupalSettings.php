<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\actions;

use Composer\Composer;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;
use UniversityOfAdelaide\ShepherdDrupalScaffold\ScaffoldTrait;
use UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\AppendFile;

/**
 * Create settings.php file and inject Shepherd-specific settings.
 *
 * Sites shouldn't commit their own settings.php. Site-specific settings should
 * be added with a `settings.app.php` adjacent to `settings.php`. This file
 * include is done with the append task below.
 */
final class DrupalSettings implements ActionInterface
{
    use ScaffoldTrait;

    public function onEvent(Event $event): void
    {
        $event->getIO()->write('Creating settings.php file if not present.');

        $drupalRootPath = $this->getDrupalRootPath();
        foreach (static::tasks($this->filesystem, $drupalRootPath) as $task) {
            $task->execute();
        }
    }

    /**
     * @return \UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\AppendFile[]
     */
    public static function tasks(Filesystem $filesystem, string $drupalRootPath): array
    {
        // settings.php comes from drupal/core-composer-scaffold.
        $settingsFile = $drupalRootPath . '/sites/default/settings.php';

        // Exit if a site has a committed settings.php with this text fragment.
        if (false !== strpos(file_get_contents($settingsFile), 'START SHEPHERD CONFIG')) {
            return [];
        }

        $settings = file_get_contents(__DIR__ . '/../../fixtures/php/settings.php.txt');
        $hashSalt = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55)));
        $data = str_replace('<<<DEFAULT_HASH_SALT>>>', $hashSalt, $settings);

        // Append Shepherd-specific environment variable settings to settings.php.
        return [new AppendFile($settingsFile, "\n" . $data)];
    }
}
