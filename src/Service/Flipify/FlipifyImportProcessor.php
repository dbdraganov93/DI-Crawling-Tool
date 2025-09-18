<?php

namespace App\Service\Flipify;

use App\Entity\FlipifyImport;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final class FlipifyImportProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FlipifyAnalyzer $analyzer,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function process(int $importId): bool
    {
        $import = null;
        $shouldProcess = false;

        $this->entityManager->wrapInTransaction(function () use ($importId, &$import, &$shouldProcess): void {
            $import = $this->entityManager->find(FlipifyImport::class, $importId, LockMode::PESSIMISTIC_WRITE);

            if (!$import instanceof FlipifyImport) {
                $this->logger->warning('Flipify import #{id} could not be processed because it no longer exists.', [
                    'id' => $importId,
                ]);

                return;
            }

            if (!$import->isPending()) {
                $this->logger->debug('Flipify import #{id} skipped because it is already {status}.', [
                    'id' => $import->getId(),
                    'status' => $import->getStatus(),
                ]);

                return;
            }

            $import->markProcessing();
            $this->entityManager->flush();
            $shouldProcess = true;
        });

        if (!$shouldProcess || !$import instanceof FlipifyImport) {
            return false;
        }

        $pdfPath = sprintf('%s/public/pdf/%s', $this->projectDir, $import->getStoredFilename());

        try {
            if (!is_file($pdfPath)) {
                throw new RuntimeException(sprintf('Stored PDF not found at "%s".', $pdfPath));
            }

            $products = $this->analyzer->analyze($pdfPath);
        } catch (Throwable $exception) {
            $this->entityManager->wrapInTransaction(function () use ($import, $exception): void {
                $this->refreshIfManaged($import);
                $import->markFailed($exception->getMessage());
                $this->entityManager->flush();
            });

            $this->logger->error('Flipify import #{id} failed: {error}', [
                'id' => $import->getId(),
                'storedFilename' => $import->getStoredFilename(),
                'error' => $exception->getMessage(),
                'exceptionClass' => $exception::class,
            ]);

            return false;
        }

        $this->entityManager->wrapInTransaction(function () use ($import, $products): void {
            $this->refreshIfManaged($import);
            $import->markCompleted($products);
            $this->entityManager->flush();
        });

        $this->logger->info('Flipify import #{id} processed successfully with {count} products.', [
            'id' => $import->getId(),
            'storedFilename' => $import->getStoredFilename(),
            'count' => $import->getProductCount(),
        ]);

        return true;
    }

    private function refreshIfManaged(FlipifyImport $import): void
    {
        if ($this->entityManager->contains($import)) {
            $this->entityManager->refresh($import);
        }
    }
}
