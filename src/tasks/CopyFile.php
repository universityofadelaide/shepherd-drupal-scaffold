<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\tasks;

use Symfony\Component\Filesystem\Filesystem;

class CopyFile implements TaskInterface
{
    protected Filesystem $filesystem;
    protected string $destination;
    protected string $origin;
    protected string $filename;
    protected bool $overwriteExisting;

    public function __construct(Filesystem $filesystem, string $destination, string $origin, string $filename, bool $overwriteExisting = false)
    {
        $this->filesystem = $filesystem;
        $this->destination = $destination;
        $this->origin = $origin;
        $this->filename = $filename;
        $this->overwriteExisting = $overwriteExisting;
    }

    public function getOrigin(): string
    {
        return $this->origin;
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
        if (!$this->overwriteExisting && $this->filesystem->exists($this->destination . '/' . $this->filename)) {
            return;
        }

        $this->filesystem->copy(
            $this->origin . '/' . $this->filename,
            $this->destination . '/' . $this->filename,
            $this->overwriteExisting,
        );
    }
}
