<?php

namespace App\Tests\Service;

use App\Dto\Brochure;
use App\Service\CsvService;
use App\Service\StoreService;
use PHPUnit\Framework\TestCase;

class CsvServiceTest extends TestCase
{
    public function testCreateCsvFromStoresCreatesFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/csv_test_' . uniqid();
        $service = new CsvService($tmpDir);

        $storeService = $this->createMock(StoreService::class);
        $storeService->method('getCompanyId')->willReturn(7);
        $storeService->method('getStores')->willReturn([
            ['storeNumber' => '1', 'city' => 'Berlin']
        ]);

        $result = $service->createCsvFromStores($storeService);
        $this->assertSame(7, $result['companyId']);
        $this->assertFileExists($result['filePath']);
        $this->assertNotEmpty($result['base64']);
        unlink($result['filePath']);
    }

    public function testCreateCsvFromBrochureCreatesFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/csv_test_' . uniqid();
        $service = new CsvService($tmpDir);

        $brochures = [
            Brochure::fromArray([
                'pdfUrl' => 'u',
            ])
        ];

        $result = $service->createCsvFromBrochure($brochures, '10');
        $this->assertSame('10', $result['companyId']);
        $this->assertFileExists($result['filePath']);
        $this->assertNotEmpty($result['base64']);
        unlink($result['filePath']);
    }
}
