<?php

namespace App\MessageHandler;

use App\Message\FlipifyImportMessage;
use App\Service\Flipify\FlipifyImportProcessor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class FlipifyImportMessageHandler
{
    public function __construct(private readonly FlipifyImportProcessor $processor)
    {
    }

    public function __invoke(FlipifyImportMessage $message): void
    {
        $this->processor->process($message->getImportId());
    }
}
