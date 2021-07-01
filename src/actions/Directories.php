<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\actions;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;
use UniversityOfAdelaide\ShepherdDrupalScaffold\ScaffoldTrait;
use UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\CreateDirectory;

/**
 * Ensure necessary directories exist.
 */
final class Directories implements ActionInterface
{
    use ScaffoldTrait;

    public function onEvent(Event $event): void
    {
        $event->getIO()->write('Creating necessary directories.');

        $root = $this->getDrupalRootPath();
        foreach (static::tasks($this->filesystem, $root) as $task) {
            $task->execute();
        }
    }

    /**
     * @return \UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\CreateDirectory[]
     */
    public static function tasks(Filesystem $filesystem, string $root): array
    {
        return array_map(fn (string $path): CreateDirectory => new CreateDirectory($filesystem, $path), [
            $root . '/modules',
            $root . '/profiles',
            $root . '/themes',
            'config-install',
            'config-export',
        ]);
    }
}
