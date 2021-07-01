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
 * Note: does nothing if the file already exists.
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
        $settingsFile = $drupalRootPath . '/sites/default/settings.php';

        // settings.php comes from drupal/core-composer-scaffold.
        if (!$filesystem->exists($settingsFile)) {
            return [];
        }

        // If we have already written to settings.php.
        if (false !== strpos(file_get_contents($settingsFile), 'START SHEPHERD CONFIG')) {
            return [];
        }

        $settings = file_get_contents(__DIR__ . '/../fixtures/php/settings.php.txt');
        $hashSalt = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55)));
        $data = str_replace('<<<DEFAULT_HASH_SALT>>>', $hashSalt, $settings);

        // Append Shepherd-specific environment variable settings to settings.php.
        return [new AppendFile($settingsFile, "\n" . $data)];
    }
}
