<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\actions;

use Composer\Composer;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;
use UniversityOfAdelaide\ShepherdDrupalScaffold\ScaffoldTrait;
use UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\AppendFile;

/**
 * Inject AU-specific htaccess settings.
 *
 * Sites shouldn't commit their own .htaccess.
 */
final class Htaccess implements ActionInterface
{
    use ScaffoldTrait;

    public function onEvent(Event $event): void
    {
        $event->getIO()->write('Ensuring .htaccess file.');

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
        // .htaccess comes from drupal/core-composer-scaffold by composer.
        $htaccessFile = $drupalRootPath . '/.htaccess';

        // If a site has .htaccess with this text fragment.
        if (str_contains(file_get_contents($htaccessFile), '# START AU CUSTOM CONFIG')) {
            // Always get the updated fixture.
            $content = file_get_contents($htaccessFile);
            $pos = strpos($content, '# START AU CUSTOM CONFIG');
            $content = substr($content, 0, $pos);
            file_put_contents($htaccessFile, $content);
        }

        $fixtureData = file_get_contents(__DIR__ . '/../../fixtures/htaccess/htaccess.txt');

        // Append AU-specific htaccess to web/.htaccess.
        return [new AppendFile($htaccessFile, "\n" . $fixtureData)];
    }
}
