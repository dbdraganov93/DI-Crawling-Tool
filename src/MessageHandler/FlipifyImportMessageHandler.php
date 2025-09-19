<?php

namespace App\MessageHandler;

use App\Message\FlipifyImportMessage;
use App\Service\Flipify\FlipifyImportProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class FlipifyImportMessageHandler
{
    public function __construct(
        private readonly FlipifyImportProcessor $processor,
        #[Autowire(service: 'monolog.logger.flipify')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(FlipifyImportMessage $message): void
    {
        $importId = $message->getImportId();

        $this->logger->info('Dequeued Flipify import from messenger transport.', [
            'importId' => $importId,
        ]);

        $processed = $this->processor->process($importId);

        $this->logger->info('Finished Flipify import message handling.', [
            'importId' => $importId,
            'processed' => $processed,
        ]);
    }
}
