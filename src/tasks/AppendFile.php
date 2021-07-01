<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\tasks;

class AppendFile implements TaskInterface
{
    protected string $filePath;
    protected $data;

    public function __construct(string $filePath, string $data)
    {
        $this->filePath = $filePath;
        $this->data = $data;
    }

    public function execute(): void
    {
        \file_put_contents($this->filePath, $this->data, \FILE_APPEND);
    }
}
