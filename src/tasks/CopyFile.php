<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\tasks;

use Symfony\Component\Filesystem\Filesystem;

class CopyFile implements TaskInterface
{
    protected string $origin;

    protected string $filename;

    protected bool $overwriteExisting;

    public function __construct(string $origin, string $filename, bool $overwriteExisting = false)
    {
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

    public function isOverwriting(): bool
    {
        return $this->overwriteExisting;
    }

    /**
     * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException
     *   When original file doesn't exist
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     *   When copy fails
     */
    public function execute(Filesystem $filesystem, string $destination): void
    {
        // Skip copying files that already exist at the destination.
        if (!$this->overwriteExisting && $filesystem->exists($destination . '/' . $this->filename)) {
            return;
        }

        $filesystem->copy(
            $this->origin . '/' . $this->filename,
            $destination . '/' . $this->filename,
            $this->overwriteExisting,
        );
    }
}
