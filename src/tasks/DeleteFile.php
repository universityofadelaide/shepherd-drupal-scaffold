<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\tasks;

use Symfony\Component\Filesystem\Filesystem;

class DeleteFile implements TaskInterface
{
    protected Filesystem $filesystem;
    protected string $path;
    protected string $filename;

    public function __construct(Filesystem $filesystem, string $path, string $filename)
    {
        $this->filesystem = $filesystem;
        $this->path = $path;
        $this->filename = $filename;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException
     *   When original file doesn't exist
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     *   When copy fails
     */
    public function execute(): void
    {
        // Skip copying files that already exist at the destination.
        if (!$this->filesystem->exists($this->path . '/' . $this->filename)) {
            return;
        }

        $this->filesystem->remove(
            $this->path . '/' . $this->filename
        );
    }
}
