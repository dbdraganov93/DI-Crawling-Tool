<?php

namespace App\Tests\Service;

use App\Dto\Brochure;
use App\Dto\Store;
use App\Service\CsvService;
use PHPUnit\Framework\TestCase;

class CsvServiceTest extends TestCase
{
    public function testCreateCsvFromStoresCreatesFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/csv_test_' . uniqid();
        $service = new CsvService($tmpDir);

        // Create a mock of the Store DTO
        $storeMock = $this->createMock(Store::class);

        // Stub the methods used in createCsvFromStores()
        $storeMock->method('getStoreNumber')->willReturn('1');
        $storeMock->method('getCity')->willReturn('Berlin');
        $storeMock->method('getZipcode')->willReturn('10115');
        $storeMock->method('getStreet')->willReturn('Main St');
        $storeMock->method('getStreetNumber')->willReturn('12');
        $storeMock->method('getLatitude')->willReturn('52.5200');
        $storeMock->method('getLongitude')->willReturn('13.4050');
        $storeMock->method('getTitle')->willReturn('Store Title');
        $storeMock->method('getSubtitle')->willReturn('');
        $storeMock->method('getText')->willReturn('Sample description');
        $storeMock->method('getPhone')->willReturn('123456789');
        $storeMock->method('getFax')->willReturn('');
        $storeMock->method('getEmail')->willReturn('');
        $storeMock->method('getStoreHours')->willReturn('');
        $storeMock->method('getStoreHoursNotes')->willReturn('');
        $storeMock->method('getPayment')->willReturn('');
        $storeMock->method('getWebsite')->willReturn('');
        $storeMock->method('getDistribution')->willReturn('');
        $storeMock->method('getParking')->willReturn('');
        $storeMock->method('getBarrierFree')->willReturn('');
        $storeMock->method('getBonusCard')->willReturn('');
        $storeMock->method('getSection')->willReturn('');
        $storeMock->method('getService')->willReturn('');
        $storeMock->method('getToilet')->willReturn('');
        $storeMock->method('getDefaultRadius')->willReturn('');

        // Provide the mocked store to the service
        $result = $service->createCsvFromStores([$storeMock], '7');
        $this->assertSame('7', $result['companyId']);
        $this->assertFileExists($result['filePath']);
        $this->assertNotEmpty($result['base64']);
        unlink($result['filePath']);
    }

    public function testCreateCsvFromBrochureCreatesFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/csv_test_' . uniqid();
        $service = new CsvService($tmpDir);

        // Create a mock Brochure
        $brochureMock = $this->createMock(Brochure::class);

        // Stub methods expected by createCsvFromBrochure
        $brochureMock->method('getBrochureNumber')->willReturn('123');
        $brochureMock->method('getType')->willReturn('default');
        $brochureMock->method('getPdfUrl')->willReturn('http://example.com/test.pdf');
        $brochureMock->method('getTitle')->willReturn('Sample Brochure');
        $brochureMock->method('getTags')->willReturn('');
        $brochureMock->method('getValidFrom')->willReturn('2023-01-01');
        $brochureMock->method('getValidTo')->willReturn('2023-01-31');
        $brochureMock->method('getVisibleFrom')->willReturn('2022-12-31');
        $brochureMock->method('getStoreNumber')->willReturn('1');
        $brochureMock->method('getSalesRegion')->willReturn('DE');
        $brochureMock->method('getVariety')->willReturn('leaflet');
        $brochureMock->method('getNational')->willReturn(0);
        $brochureMock->method('getGender')->willReturn('all');
        $brochureMock->method('getAgeRange')->willReturn('18-65');
        $brochureMock->method('getTrackingPixels')->willReturn('');
        $brochureMock->method('getPdfProcessingOptions')->willReturn([]);
        $brochureMock->method('getLangCode')->willReturn('de');
        $brochureMock->method('getZipcode')->willReturn('10115');
        $brochureMock->method('getLayout')->willReturn('single-page');

        $result = $service->createCsvFromBrochure([$brochureMock], '10');
        $this->assertSame('10', $result['companyId']);
        $this->assertFileExists($result['filePath']);
        $this->assertNotEmpty($result['base64']);
        unlink($result['filePath']);
    }
}
