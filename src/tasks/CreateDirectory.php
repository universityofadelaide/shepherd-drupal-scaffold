<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\tasks;

use Symfony\Component\Filesystem\Filesystem;

class CreateDirectory implements TaskInterface
{
    protected string $path;

    protected bool $gitKeep;

    public function __construct(string $path, bool $gitKeep = true)
    {
        $this->path = $path;
        $this->gitKeep = $gitKeep;
    }

    /**
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     *   On any directory creation failure or when touch fails
     */
    public function execute(Filesystem $filesystem): void
    {
        if (!$filesystem->exists($this->path)) {
            $filesystem->mkdir($this->path);
        }

        if ($this->gitKeep) {
            $filesystem->touch($this->path . '/.gitkeep');
        }
    }
}
