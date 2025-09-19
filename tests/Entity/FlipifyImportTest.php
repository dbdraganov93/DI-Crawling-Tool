<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\FlipifyImport;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FlipifyImportTest extends TestCase
{
    public function testNewImportStartsPending(): void
    {
        $import = new FlipifyImport('brochure.pdf', 'stored.pdf');

        self::assertTrue($import->isPending());
        self::assertFalse($import->isProcessing());
        self::assertNull($import->getProcessedAt());
        self::assertNull($import->getProcessingStartedAt());
        self::assertSame([], $import->getProducts());
    }

    public function testMarkCompletedStoresProductsAndTimestamp(): void
    {
        $import = new FlipifyImport('brochure.pdf', 'stored.pdf');
        $products = [
            ['name' => 'Sample product', 'price' => 9.99],
        ];

        $import->markCompleted($products);

        self::assertTrue($import->isCompleted());
        self::assertSame($products, $import->getProducts());
        self::assertNotNull($import->getProcessedAt());
        self::assertNull($import->getErrorMessage());
    }

    public function testMarkFailedClearsProductsAndStoresError(): void
    {
        $import = new FlipifyImport('brochure.pdf', 'stored.pdf');
        $errorMessage = str_repeat('a', 2000);

        $import->markFailed($errorMessage);

        self::assertTrue($import->isFailed());
        self::assertSame([], $import->getProducts());
        self::assertNotNull($import->getProcessedAt());
        self::assertNotNull($import->getErrorMessage());
        self::assertLessThanOrEqual(1000, strlen((string) $import->getErrorMessage()));
    }

    public function testMarkProcessingResetsState(): void
    {
        $import = new FlipifyImport('brochure.pdf', 'stored.pdf');
        $import->markFailed('Error');

        $import->markProcessing();

        self::assertTrue($import->isProcessing());
        self::assertSame([], $import->getProducts());
        self::assertNull($import->getProcessedAt());
        self::assertNull($import->getErrorMessage());
        self::assertNotNull($import->getProcessingStartedAt());
    }

    public function testProcessingTimeoutDetection(): void
    {
        $import = new FlipifyImport('brochure.pdf', 'stored.pdf');
        $startedAt = new DateTimeImmutable('-15 minutes');

        $import->markProcessing($startedAt);

        self::assertTrue($import->hasProcessingTimedOut(600));
        self::assertFalse($import->hasProcessingTimedOut(3600));

        $import->markCompleted([]);

        self::assertFalse($import->hasProcessingTimedOut(10));
    }
}
