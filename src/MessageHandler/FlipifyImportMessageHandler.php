<?php

namespace App\MessageHandler;

use App\Entity\FlipifyImport;
use App\Message\FlipifyImportMessage;
use App\Repository\FlipifyImportRepository;
use App\Service\Flipify\FlipifyAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final class FlipifyImportMessageHandler
{
    public function __construct(
        private readonly FlipifyImportRepository $repository,
        private readonly FlipifyAnalyzer $analyzer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function __invoke(FlipifyImportMessage $message): void
    {
        if (function_exists('set_time_limit')) {
            try {
                set_time_limit(0);
            } catch (Throwable) {
                // Some hosting environments forbid altering the time limit. Ignore silently.
            }
        }

        $import = $this->repository->find($message->getImportId());

        if (!$import instanceof FlipifyImport) {
            $this->logger->warning('Flipify import #{id} could not be processed because it no longer exists.', [
                'id' => $message->getImportId(),
            ]);

            return;
        }

        $import->markProcessing();
        $this->entityManager->flush();

        $pdfPath = sprintf('%s/public/pdf/%s', $this->projectDir, $import->getStoredFilename());

        try {
            if (!is_file($pdfPath)) {
                throw new RuntimeException(sprintf('Stored PDF not found at "%s".', $pdfPath));
            }

            $products = $this->analyzer->analyze($pdfPath);
            $import->markCompleted($products);
        } catch (Throwable $exception) {
            $import->markFailed($exception->getMessage());
            $this->entityManager->flush();

            $this->logger->error('Flipify import #{id} failed: {error}', [
                'id' => $import->getId(),
                'storedFilename' => $import->getStoredFilename(),
                'error' => $exception->getMessage(),
                'exceptionClass' => $exception::class,
            ]);

            return;
        }

        $this->entityManager->flush();

        $this->logger->info('Flipify import #{id} processed successfully with {count} products.', [
            'id' => $import->getId(),
            'storedFilename' => $import->getStoredFilename(),
            'count' => $import->getProductCount(),
        ]);
    }
}
