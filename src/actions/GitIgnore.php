<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\actions;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;
use UniversityOfAdelaide\ShepherdDrupalScaffold\ScaffoldTrait;
use UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\AppendFile;
use UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\CopyFile;

/**
 * Adds scaffold files to .gitignore.
 */
final class GitIgnore implements ActionInterface
{
    use ScaffoldTrait;

    public function onEvent(Event $event): void
    {
        $event->getIO()->write('Adding scaffold files to .gitignore');

        $scaffoldPath = $this->getScaffoldDirectory();
        $projectPath = $this->getProjectPath();
        foreach (static::tasks($this->filesystem, $scaffoldPath, $projectPath) as $task) {
            $task->execute();
        }
    }

    /**
     * @return \UniversityOfAdelaide\ShepherdDrupalScaffold\tasks\AppendFile[]
     */
    public static function tasks(Filesystem $filesystem, string $scaffoldPath, string $projectPath): array
    {
        // Only continue if there is a .gitignore file.
        $gitIgnorePath = $projectPath . '/.gitignore';
        if (!$filesystem->exists($gitIgnorePath)) {
            return [];
        }

        $gitIgnore = file_get_contents($gitIgnorePath);

        // Get list of file paths which need to be added to .gitignore.
        $requiredPath = $scaffoldPath . '/required';
        $fileTasks = ScaffoldFiles::tasks($filesystem, $requiredPath, $projectPath);
        $paths = array_filter(
            array_map(fn (CopyFile $task) => $task->getFilename(), $fileTasks),
            fn (string $fileName): bool => false === strpos($gitIgnore, $fileName)
        );

        $data = '';
        foreach ($paths as $path) {
            $data .= sprintf("%s\n", $path);
        }

        if (!empty($data)) {
            return [new AppendFile($gitIgnorePath, "\n" . $data)];
        }

        return [];
    }
}
