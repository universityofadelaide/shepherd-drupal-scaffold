<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\tasks;

use Symfony\Component\Filesystem\Filesystem;

class CreateDirectory implements TaskInterface
{
    protected string $path;
    protected bool $gitKeep;
    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem, string $path, bool $gitKeep = true)
    {
        $this->filesystem = $filesystem;
        $this->path = $path;
        $this->gitKeep = $gitKeep;
    }

    /**
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     *   On any directory creation failure or when touch fails
     */
    public function execute(): void
    {
        if (!$this->filesystem->exists($this->path)) {
            $this->filesystem->mkdir($this->path);
        }

        if ($this->gitKeep) {
            $this->filesystem->touch($this->path . '/.gitkeep');
        }
    }
}
