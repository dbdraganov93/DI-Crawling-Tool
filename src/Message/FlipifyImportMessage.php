<?php

namespace App\Message;

final class FlipifyImportMessage
{
    public function __construct(private readonly int $importId)
    {
    }

    public function getImportId(): int
    {
        return $this->importId;
    }
}
