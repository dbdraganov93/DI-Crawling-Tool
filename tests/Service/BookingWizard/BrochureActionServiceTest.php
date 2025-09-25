<?php

declare(strict_types=1);

namespace App\Tests\Service\BookingWizard;

use App\Dto\Brochure;
use App\Service\BookingWizard\BrochureActionService;
use App\Service\CsvService;
use App\Service\IprotoService;
use PHPUnit\Framework\TestCase;

class BrochureActionServiceTest extends TestCase
{
    public function testDuplicateBrochuresPerStoreCreatesCopiesForEachStore(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $csvService = $this->createMock(CsvService::class);

        $service = new BrochureActionService($iprotoService, $csvService);

        $iprotoService
            ->expects($this->once())
            ->method('getStoresByCompany')
            ->with('42')
            ->willReturn([
                ['storeNumber' => '100'],
                ['storeNumber' => '200'],
            ]);

        $brochureDetail = [
            'id' => '55',
            'brochureNumber' => 'BR-01',
            'title' => 'Weekly Deals',
            'type' => 'default',
            'variety' => 'leaflet',
            'validFrom' => '2025-01-01T00:00:00Z',
            'validTo' => '2025-01-07T23:59:59Z',
            'visibleFrom' => '2024-12-31T00:00:00Z',
            'trackingPixels' => ['https://tracker.example/pixel'],
            'layout' => 'single-page',
            'languageCode' => 'en',
            'salesRegion' => 'National',
            'integration' => '/api/integrations/42',
        ];

        $iprotoService
            ->expects($this->once())
            ->method('getBrochureDetails')
            ->with('55')
            ->willReturn($brochureDetail);

        $csvService
            ->expects($this->once())
            ->method('createCsvFromBrochure')
            ->with(
                $this->callback(function ($brochures) {
                    if (!is_array($brochures) || count($brochures) !== 2) {
                        return false;
                    }

                    $first = $brochures[0];
                    $second = $brochures[1];

                    return $first instanceof Brochure
                        && $second instanceof Brochure
                        && $first->getBrochureNumber() === 'BR-01_100'
                        && $second->getBrochureNumber() === 'BR-01_200'
                        && $first->getStoreNumber() === '100'
                        && $second->getStoreNumber() === '200';
                }),
                '42'
            )
            ->willReturn([
                'downloadLink' => 'https://example.com/brochures.csv',
            ]);

        $iprotoService
            ->expects($this->once())
            ->method('importData')
            ->with([
                'downloadLink' => 'https://example.com/brochures.csv',
            ])
            ->willReturn([
                '@id' => '/api/imports/999',
                'status' => 'queued',
            ]);

        $result = $service->duplicateBrochuresPerStore('42', '9', ['55']);

        $this->assertSame(1, $result['summary']['selectedBrochures']);
        $this->assertSame(2, $result['summary']['stores']);
        $this->assertSame(2, $result['summary']['generated']);
        $this->assertSame('999', $result['importId']);
        $this->assertSame('queued', $result['import']['status']);
    }

    public function testDuplicateBrochuresPerStoreRequiresSelection(): void
    {
        $service = new BrochureActionService(
            $this->createMock(IprotoService::class),
            $this->createMock(CsvService::class)
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->duplicateBrochuresPerStore('42', '9', []);
    }

    public function testDuplicateBrochuresPerStoreRequiresStores(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $iprotoService
            ->expects($this->once())
            ->method('getStoresByCompany')
            ->with('42')
            ->willReturn([]);

        $service = new BrochureActionService($iprotoService, $this->createMock(CsvService::class));

        $this->expectException(\RuntimeException::class);
        $service->duplicateBrochuresPerStore('42', '9', ['1']);
    }

    public function testDuplicateBrochuresPerStoreWrapsImportFailures(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $csvService = $this->createMock(CsvService::class);

        $service = new BrochureActionService($iprotoService, $csvService);

        $iprotoService
            ->expects($this->once())
            ->method('getStoresByCompany')
            ->with('42')
            ->willReturn([
                ['storeNumber' => '100'],
            ]);

        $iprotoService
            ->expects($this->once())
            ->method('getBrochureDetails')
            ->with('55')
            ->willReturn([
                'id' => '55',
                'brochureNumber' => 'BR-01',
                'integration' => '/api/integrations/42',
            ]);

        $csvService
            ->expects($this->once())
            ->method('createCsvFromBrochure')
            ->with($this->isType('array'), '42')
            ->willReturn([
                'companyId' => '42',
                'type' => 'brochures',
                'base64' => 'ZHVtbXk=',
            ]);

        $iprotoService
            ->expects($this->once())
            ->method('importData')
            ->willThrowException(new \TypeError('Boom'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to import duplicated brochures.');

        $service->duplicateBrochuresPerStore('42', '9', ['55']);
    }
}
