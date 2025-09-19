<?php

namespace App\Service\Flipify;

final class PdfImageSet
{
    /**
     * @param non-empty-string $directory
     * @param list<string>     $paths
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $paths,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    public function isEmpty(): bool
    {
        return $this->paths === [];
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function cleanup(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        if (is_dir($this->directory)) {
            @rmdir($this->directory);
        }

        $parentDirectory = \dirname($this->directory);
        if (is_dir($parentDirectory) && $parentDirectory !== sys_get_temp_dir()) {
            @rmdir($parentDirectory);
        }
    }
}
