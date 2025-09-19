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
        #[Autowire(service: 'monolog.logger.flipify')]
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function process(int $importId): bool
    {
        $this->logger->info('Attempting to process Flipify import.', [
            'importId' => $importId,
        ]);

        $import = null;
        $shouldProcess = false;

        $this->entityManager->wrapInTransaction(function () use ($importId, &$import, &$shouldProcess): void {
            $import = $this->entityManager->find(FlipifyImport::class, $importId, LockMode::PESSIMISTIC_WRITE);

            if (!$import instanceof FlipifyImport) {
                $this->logger->warning('Flipify import could not be processed because it no longer exists.', [
                    'importId' => $importId,
                ]);

                return;
            }

            if (!$import->isPending()) {
                $this->logger->info('Flipify import skipped because status no longer pending.', [
                    'importId' => $import->getId(),
                    'status' => $import->getStatus(),
                ]);

                return;
            }

            $import->markProcessing();
            $this->entityManager->flush();
            $shouldProcess = true;

            $this->logger->info('Flipify import marked as processing.', [
                'importId' => $import->getId(),
            ]);
        });

        if (!$shouldProcess || !$import instanceof FlipifyImport) {
            $this->logger->debug('Flipify import processing skipped after transactional claim.', [
                'importId' => $importId,
            ]);

            return false;
        }

        $pdfPath = sprintf('%s/public/pdf/%s', $this->projectDir, $import->getStoredFilename());

        $this->logger->debug('Resolved Flipify import PDF path.', [
            'importId' => $import->getId(),
            'pdfPath' => $pdfPath,
        ]);

        try {
            if (!is_file($pdfPath)) {
                throw new RuntimeException(sprintf('Stored PDF not found at "%s".', $pdfPath));
            }

            $fileSize = @filesize($pdfPath) ?: null;
            $analysisStartedAt = microtime(true);

            $this->logger->info('Starting Flipify analysis for brochure.', [
                'importId' => $import->getId(),
                'pdfPath' => $pdfPath,
                'fileSize' => $fileSize,
            ]);

            $products = $this->analyzer->analyze($pdfPath);
            $durationMs = (int) round((microtime(true) - $analysisStartedAt) * 1000);

            $this->logger->info('Flipify analysis completed.', [
                'importId' => $import->getId(),
                'duration_ms' => $durationMs,
                'productCount' => \count($products),
            ]);
        } catch (Throwable $exception) {
            $durationMs = isset($analysisStartedAt)
                ? (int) round((microtime(true) - $analysisStartedAt) * 1000)
                : null;

            $this->entityManager->wrapInTransaction(function () use ($import, $exception): void {
                $this->refreshIfManaged($import);
                $import->markFailed($exception->getMessage());
                $this->entityManager->flush();
            });

            $this->logger->error('Flipify import failed during analysis.', [
                'importId' => $import->getId(),
                'storedFilename' => $import->getStoredFilename(),
                'error' => $exception->getMessage(),
                'exceptionClass' => $exception::class,
                'duration_ms' => $durationMs,
            ]);

            return false;
        }

        $this->entityManager->wrapInTransaction(function () use ($import, $products): void {
            $this->refreshIfManaged($import);
            $import->markCompleted($products);
            $this->entityManager->flush();
        });

        $this->logger->info('Flipify import processed successfully.', [
            'importId' => $import->getId(),
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
