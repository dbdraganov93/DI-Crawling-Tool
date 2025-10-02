<?php

declare(strict_types=1);

namespace App\Tests\Service\BookingWizard;

use App\Dto\Brochure;
use App\Dto\Product;
use App\Service\BookingWizard\BrochureActionService;
use App\Service\CsvService;
use App\Service\IprotoService;
use App\Service\S3Service;
use PHPUnit\Framework\TestCase;
use function mb_check_encoding;

class BrochureActionServiceTest extends TestCase
{
    public function testDuplicateBrochuresPerStoreCreatesCopiesForEachStore(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $csvService = $this->createMock(CsvService::class);

        $service = $this->createBrochureActionService($iprotoService, $csvService);

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

    public function testDuplicateBrochuresPerSelectedStoresHonoursStoreSelection(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $csvService = $this->createMock(CsvService::class);

        $service = $this->createBrochureActionService($iprotoService, $csvService);

        $iprotoService
            ->expects($this->once())
            ->method('getStoresByCompany')
            ->with('42')
            ->willReturn([
                ['storeNumber' => '100'],
                ['storeNumber' => '200'],
                ['storeNumber' => '300'],
            ]);

        $brochureDetail = [
            'id' => '55',
            'brochureNumber' => 'BR-01',
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
                        && $first->getStoreNumber() === '200'
                        && $first->getBrochureNumber() === 'BR-01_200'
                        && $second->getStoreNumber() === '100'
                        && $second->getBrochureNumber() === 'BR-01_100';
                }),
                '42'
            )
            ->willReturn([
                'companyId' => '42',
            ]);

        $iprotoService
            ->expects($this->once())
            ->method('importData')
            ->with([
                'companyId' => '42',
            ])
            ->willReturn([
                '@id' => '/api/imports/100',
                'status' => 'queued',
            ]);

        $result = $service->duplicateBrochuresPerSelectedStores('42', '9', ['55'], ['200', '100', '100']);

        $this->assertSame(1, $result['summary']['selectedBrochures']);
        $this->assertSame(2, $result['summary']['stores']);
        $this->assertSame(2, $result['summary']['generated']);
    }

    public function testDuplicateBrochuresPerSelectedStoresRejectsUnknownStores(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $csvService = $this->createMock(CsvService::class);

        $service = $this->createBrochureActionService($iprotoService, $csvService);

        $iprotoService
            ->expects($this->once())
            ->method('getStoresByCompany')
            ->with('42')
            ->willReturn([
                ['storeNumber' => '100'],
            ]);

        $iprotoService
            ->expects($this->never())
            ->method('getBrochureDetails');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not available');

        $service->duplicateBrochuresPerSelectedStores('42', '9', ['55'], ['999']);
    }

    public function testDuplicateBrochuresPerSelectedStoresRequiresSelection(): void
    {
        $service = $this->createBrochureActionService(
            $this->createMock(IprotoService::class),
            $this->createMock(CsvService::class)
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Select at least one store');

        $service->duplicateBrochuresPerSelectedStores('42', '9', ['55'], []);
    }

    public function testDuplicateBrochuresPerStoreRequiresSelection(): void
    {
        $service = $this->createBrochureActionService(
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

        $service = $this->createBrochureActionService($iprotoService, $this->createMock(CsvService::class));

        $this->expectException(\RuntimeException::class);
        $service->duplicateBrochuresPerStore('42', '9', ['1']);
    }

    public function testDuplicateBrochuresPerStoreWrapsImportFailures(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $csvService = $this->createMock(CsvService::class);

        $service = $this->createBrochureActionService($iprotoService, $csvService);

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

    public function testDuplicateBrochuresPerStoreSanitizesInvalidStrings(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $csvService = $this->createMock(CsvService::class);

        $service = $this->createBrochureActionService($iprotoService, $csvService);

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
                'brochureNumber' => 'BR-Ü',
                'title' => "Sommer Angebote \xC3\x28",
                'tags' => [
                    ['name' => 'Promo'],
                    ['title' => "Sonder \xC3\x28"],
                ],
                'trackingPixels' => [
                    ['url' => 'https://tracker.example/pixel?a=1'],
                    ['code' => '<script>track()</script>'],
                ],
                'pages' => [
                    ['pdfUrl' => 'https://cdn.example/brochure.pdf'],
                ],
                'integration' => '/api/integrations/42',
            ]);

        $csvService
            ->expects($this->once())
            ->method('createCsvFromBrochure')
            ->with(
                $this->callback(function ($brochures) {
                    $this->assertIsArray($brochures);
                    $this->assertCount(1, $brochures);
                    $brochure = $brochures[0];
                    $this->assertInstanceOf(Brochure::class, $brochure);
                    $this->assertSame('BR-Ü_100', $brochure->getBrochureNumber());
                    $this->assertSame('https://cdn.example/brochure.pdf', $brochure->getPdfUrl());
                    $this->assertTrue(mb_check_encoding($brochure->getTitle(), 'UTF-8'));
                    $this->assertStringNotContainsString("\xC3\x28", $brochure->getTitle());
                    $this->assertSame(
                        "https://tracker.example/pixel?a=1\n<script>track()</script>",
                        $brochure->getTrackingPixels()
                    );
                    $tags = $brochure->getTags();
                    $this->assertTrue(mb_check_encoding($tags, 'UTF-8'));
                    $this->assertStringContainsString('Promo', $tags);
                    $this->assertStringContainsString('Sonder', $tags);
                    $this->assertStringNotContainsString("\xC3\x28", $tags);

                    return true;
                }),
                '42'
            )
            ->willReturn([
                'companyId' => '42',
                'type' => 'brochures',
                'base64' => 'ZHVtbXk=',
            ]);

        $iprotoService
            ->expects($this->once())
            ->method('importData')
            ->willReturn([
                '@id' => '/api/imports/999',
                'status' => 'queued',
            ]);

        $result = $service->duplicateBrochuresPerStore('42', '9', ['55']);

        $this->assertSame('999', $result['importId']);
        $this->assertSame('queued', $result['import']['status']);
    }

    public function testDuplicateBrochuresPerStoreUploadsPdfToS3WhenBrochurePageUrlProvided(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $csvService = $this->createMock(CsvService::class);
        $s3Service = $this->createMock(S3Service::class);

        $pdfDir = sys_get_temp_dir() . '/brochure-action/' . uniqid('pdf-', true);

        $service = new BrochureActionService(
            $iprotoService,
            $csvService,
            $s3Service,
            $pdfDir,
            static function (int $seconds): void {
                // no-op for tests
            },
            7,
            0,
        );

        $iprotoService
            ->expects($this->once())
            ->method('getStoresByCompany')
            ->with('42')
            ->willReturn([
                ['storeNumber' => '100'],
                ['storeNumber' => '200'],
            ]);

        $iprotoService
            ->expects($this->once())
            ->method('getBrochureDetails')
            ->with('55')
            ->willReturn([
                'id' => '55',
                'brochureNumber' => 'BR-01',
                'integration' => '/api/integrations/42',
                'pdfUrl' => '/api/brochure_pages/1442340',
            ]);

        $iprotoService
            ->expects($this->once())
            ->method('downloadBrochurePdf')
            ->with(
                '1442340',
                $this->callback(function (string $destination) use ($pdfDir) {
                    $this->assertStringStartsWith($pdfDir, $destination);
                    return true;
                }),
                '55'
            )
            ->willReturnCallback(static function (string $pageId, string $destination, string $brochureId): string {
                if (!is_dir(dirname($destination))) {
                    mkdir(dirname($destination), 0755, true);
                }

                file_put_contents($destination, 'PDF-' . $pageId . '-' . $brochureId);

                return $destination;
            });

        $s3Service
            ->expects($this->once())
            ->method('upload')
            ->with($this->callback(function (string $localPath) use ($pdfDir) {
                $this->assertStringStartsWith($pdfDir, $localPath);
                $this->assertFileExists($localPath);

                return true;
            }))
            ->willReturn('https://s3.example/brochure_55.pdf');

        $csvService
            ->expects($this->once())
            ->method('createCsvFromBrochure')
            ->with(
                $this->callback(function (array $brochures) {
                    $this->assertCount(2, $brochures);
                    $first = $brochures[0];
                    $second = $brochures[1];

                    $this->assertSame('https://s3.example/brochure_55.pdf', $first->getPdfUrl());
                    $this->assertSame('https://s3.example/brochure_55.pdf', $second->getPdfUrl());

                    return true;
                }),
                '42'
            )
            ->willReturn([
                'downloadLink' => 'https://example.com/brochures.csv',
            ]);

        $iprotoService
            ->expects($this->once())
            ->method('importData')
            ->willReturn([
                '@id' => '/api/imports/888',
                'status' => 'queued',
            ]);

        $result = $service->duplicateBrochuresPerStore('42', '9', ['55']);

        $this->assertSame('888', $result['importId']);
        $this->assertSame('queued', $result['import']['status']);
    }

    public function testDuplicateDiscoverBrochuresDuplicateProductsPerStore(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $csvService = $this->createMock(CsvService::class);

        $service = $this->createBrochureActionService($iprotoService, $csvService);

        $iprotoService
            ->expects($this->once())
            ->method('getStoresByCompany')
            ->with('42')
            ->willReturn([
                ['storeNumber' => '100'],
                ['storeNumber' => '200'],
            ]);

        $layout = [
            '3' => [
                'pages' => [
                    [
                        'modules' => [
                            [
                                'name' => 'product_slot',
                                'products' => [
                                    ['id' => 11],
                                    ['id' => 22],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $brochureDetail = [
            'id' => '55',
            'brochureNumber' => 'DISC-01',
            'integration' => '/api/integrations/42',
            'type' => 'discover',
            'layout' => json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $iprotoService
            ->expects($this->once())
            ->method('getBrochureDetails')
            ->with('55')
            ->willReturn($brochureDetail);

        $iprotoService
            ->expects($this->exactly(2))
            ->method('getProduct')
            ->willReturnMap([
                [11, ['productNumber' => 'P-11', 'integration' => '/api/integrations/42']],
                [22, ['productNumber' => 'P-22', 'integration' => '/api/integrations/42']],
            ]);

        $csvService
            ->expects($this->once())
            ->method('createCsvFromProducts')
            ->with(
                $this->callback(function (array $products): bool {
                    if (count($products) !== 4) {
                        return false;
                    }

                    $numbers = array_map(static function (Product $product): string {
                        return $product->getProductNumber();
                    }, $products);

                    sort($numbers);

                    return $numbers === ['P-11_100', 'P-11_200', 'P-22_100', 'P-22_200'];
                }),
                '42'
            )
            ->willReturn([
                'type' => 'products',
                'companyId' => '42',
                'base64' => 'products',
            ]);

        $iprotoService
            ->expects($this->exactly(2))
            ->method('importData')
            ->willReturnOnConsecutiveCalls(
                ['@id' => '/api/imports/555'],
                ['@id' => '/api/imports/999', 'status' => 'queued']
            );

        $iprotoService
            ->expects($this->exactly(4))
            ->method('findProductsByNumber')
            ->willReturnMap([
                ['42', 'P-11_100', ['id' => 1011]],
                ['42', 'P-11_200', ['id' => 2011]],
                ['42', 'P-22_100', ['id' => 1022]],
                ['42', 'P-22_200', ['id' => 2022]],
            ]);

        $csvService
            ->expects($this->once())
            ->method('createCsvFromBrochure')
            ->with(
                $this->callback(function (array $brochures): bool {
                    if (count($brochures) !== 2) {
                        return false;
                    }

                    $expectedLayouts = [
                        '100' => [1011, 1022],
                        '200' => [2011, 2022],
                    ];

                    foreach ($brochures as $brochure) {
                        if (!$brochure instanceof Brochure) {
                            return false;
                        }

                        $decoded = json_decode($brochure->getLayout(), true);
                        if (!is_array($decoded)) {
                            return false;
                        }

                        $ids = [];
                        foreach ($decoded as $block) {
                            if (!is_array($block)) {
                                continue;
                            }

                            foreach ($block['pages'] ?? [] as $page) {
                                if (!is_array($page)) {
                                    continue;
                                }

                                foreach ($page['modules'] ?? [] as $module) {
                                    if (!is_array($module)) {
                                        continue;
                                    }

                                    foreach ($module['products'] ?? [] as $product) {
                                        if (is_array($product) && isset($product['id'])) {
                                            $ids[] = $product['id'];
                                        }
                                    }
                                }
                            }
                        }

                        sort($ids);

                        $storeNumber = $brochure->getStoreNumber();

                        if (!isset($expectedLayouts[$storeNumber]) || $ids !== $expectedLayouts[$storeNumber]) {
                            return false;
                        }
                    }

                    return true;
                }),
                '42'
            )
            ->willReturn([
                'downloadLink' => 'https://example.com/brochures.csv',
            ]);

        $result = $service->duplicateBrochuresPerStore('42', '9', ['55']);

        $this->assertSame('999', $result['importId']);
        $this->assertSame(2, $result['summary']['stores']);
        $this->assertSame(2, $result['summary']['generated']);
    }

    public function testDuplicateBrochuresPerStoreWrapsCsvFailures(): void
    {
        $iprotoService = $this->createMock(IprotoService::class);
        $csvService = $this->createMock(CsvService::class);

        $service = $this->createBrochureActionService($iprotoService, $csvService);

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
            ->willThrowException(new \Exception('csv exploded'));

        $iprotoService
            ->expects($this->never())
            ->method('importData');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create brochure CSV for duplication.');

        $service->duplicateBrochuresPerStore('42', '9', ['55']);
    }

    private function createBrochureActionService(
        ?IprotoService $iprotoService = null,
        ?CsvService $csvService = null,
        ?S3Service $s3Service = null,
        ?string $pdfDir = null
    ): BrochureActionService {
        $iprotoService ??= $this->createMock(IprotoService::class);
        $csvService ??= $this->createMock(CsvService::class);
        $s3Service ??= $this->createMock(S3Service::class);

        $s3Service->method('upload')->willReturnCallback(
            static fn (string $path): string => 'https://s3.example/' . basename($path)
        );

        $pdfDir ??= sys_get_temp_dir() . '/brochure-action/' . uniqid('', true);

        return new BrochureActionService(
            $iprotoService,
            $csvService,
            $s3Service,
            $pdfDir,
            static function (int $seconds): void {
                // no-op for tests
            },
            7,
            0,
        );
    }
}
