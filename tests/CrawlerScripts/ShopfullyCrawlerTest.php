<?php

namespace App\Tests\CrawlerScripts;

use App\CrawlerScripts\ShopfullyCrawler;
use App\Service\ShopfullyService;
use App\Service\IprotoService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use App\Service\StoreService;
use App\Service\BrochureService;
use App\Entity\ShopfullyLog;

class ShopfullyCrawlerTest extends TestCase
{
    private function getCrawler(
        EntityManagerInterface $em = null,
        ShopfullyService $shopfullyService = null,
        IprotoService $iprotoService = null
    ): ShopfullyCrawler {
        $em = $em ?: $this->createMock(EntityManagerInterface::class);
        $shopfullyService = $shopfullyService ?: $this->createMock(ShopfullyService::class);
        $iprotoService = $iprotoService ?: $this->createMock(IprotoService::class);

        return new ShopfullyCrawler($em, $shopfullyService, $iprotoService);
    }

    public function testCreateStoresAddsStores(): void
    {
        $crawler = $this->getCrawler();
        $storeService = new StoreService(5);

        $data = [
            'brochureStores' => [
                ['Store' => ['id' => '1', 'city' => 'Berlin', 'zip' => '10115', 'address' => 'S', 'lat' => '1', 'lng' => '2', 'more_info' => 'T', 'description' => 'D', 'phone' => 'p', 'fax' => 'f']],
                ['Store' => ['id' => '2', 'city' => 'Hamburg', 'zip' => '20095', 'address' => 'S', 'lat' => '3', 'lng' => '4', 'more_info' => 'T2', 'description' => 'D2', 'phone' => 'p2', 'fax' => 'f2']],
            ],
        ];

        $ref = new \ReflectionClass($crawler);
        $method = $ref->getMethod('createStores');
        $method->setAccessible(true);
        $method->invoke($crawler, $data, $storeService);

        $stores = $storeService->getStores();
        $this->assertCount(2, $stores);
        $this->assertSame('1', $stores[0]['storeNumber']);
        $this->assertSame('Hamburg', $stores[1]['city']);
    }

    public function testCreateBrochureAddsBrochure(): void
    {
        $crawler = $this->getCrawler();
        $brochureService = new BrochureService(10);

        $data = [
            'publicationData' => ['data' => [['Publication' => ['pdf_url' => 'http://example.com/p.pdf']]]],
            'brochureData' => ['data' => [['Flyer' => [
                'id' => 'B1',
                'title' => 'Title',
                'start_date' => '2023-01-01',
                'end_date' => '2023-01-02',
                'stores' => '1'
            ]]]],
            'trackingPixel' => 'pix'
        ];

        $ref = new \ReflectionClass($crawler);
        $method = $ref->getMethod('createBrochure');
        $method->setAccessible(true);
        $method->invoke($crawler, $data, $brochureService);

        $brochures = $brochureService->getBrochures();
        $this->assertCount(1, $brochures);
        $this->assertSame('Title', $brochures[0]['title']);
        $this->assertSame('B1', $brochures[0]['brochureNumber']);
    }

    public function testLogPersistsEntity(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $shopfullyService = $this->createMock(ShopfullyService::class);
        $iprotoService = $this->createMock(IprotoService::class);

        $logs = [];
        $em->expects($this->exactly(1))->method('persist')->with($this->callback(function ($log) use (&$logs) {
            $logs[] = $log;
            return $log instanceof ShopfullyLog;
        }));
        $em->expects($this->once())->method('flush');

        $crawler = new ShopfullyCrawler($em, $shopfullyService, $iprotoService);
        $refProp = new \ReflectionProperty($crawler, 'company');
        $refProp->setAccessible(true);
        $refProp->setValue($crawler, 7);

        $ref = new \ReflectionClass($crawler);
        $method = $ref->getMethod('log');
        $method->setAccessible(true);
        $formData = ['company' => 7, 'locale' => 'de_de', 'numbers' => []];
        $method->invoke($crawler, 'de_de', $formData, ['@id' => '/imports/5', 'status' => 'ok'], 'stores');

        $this->assertCount(1, $logs);
        $this->assertSame('stores', $logs[0]->getImportType());
        $this->assertSame(7, $logs[0]->getIprotoId());
        $this->assertSame($formData, $logs[0]->getData());
        $this->assertSame(0, $logs[0]->getReimportCount());
    }

    public function testCrawlRunsThroughFlow(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $shopfullyService = $this->createMock(ShopfullyService::class);
        $iprotoService = $this->createMock(IprotoService::class);

        $brochureData = [
            'publicationData' => ['data' => [['Publication' => ['pdf_url' => 'http://example.com/p.pdf']]]],
            'brochureData' => ['data' => [['Flyer' => [
                'id' => 'B1',
                'title' => 'Title',
                'stores' => '1'
            ]]]],
            'brochureStores' => [
                ['Store' => ['id' => '1', 'city' => 'Berlin', 'zip' => '10115', 'address' => 'S', 'lat' => '1', 'lng' => '2', 'more_info' => 'T', 'description' => 'D', 'phone' => 'p', 'fax' => 'f']]
            ]
        ];

        $shopfullyService->expects($this->once())
            ->method('getBrochure')
            ->with('123', 'de_de')
            ->willReturn($brochureData);

        $iprotoService->expects($this->exactly(2))
            ->method('importData')
            ->willReturn(['@id' => '/imports/9', 'status' => 'ok']);

        $logs = [];
        $em->expects($this->exactly(2))->method('persist')->with($this->callback(function ($log) use (&$logs) {
            $logs[] = $log;
            return true;
        }));
        $em->expects($this->exactly(2))->method('flush');

        $crawler = new ShopfullyCrawler($em, $shopfullyService, $iprotoService);

        $data = [
            'company' => 42,
            'locale' => 'de_de',
            'numbers' => [[
                'number' => '123',
                'tracking_pixel' => 'pix',
                'validity_start' => new \DateTime('2023-01-01'),
                'validity_end' => new \DateTime('2023-01-02'),
                'visibility_start' => new \DateTime('2023-01-01'),
            ]]
        ];

        $cwd = getcwd();
        $tmp = sys_get_temp_dir() . '/shopfully_test_' . uniqid();
        mkdir($tmp);
        chdir($tmp);

        $crawler->crawl($data);

        chdir($cwd);
        foreach (glob($tmp . '/public/csv/*.csv') as $file) {
            unlink($file);
        }
        if (is_dir($tmp . '/public/csv')) {
            rmdir($tmp . '/public/csv');
        }
        if (is_dir($tmp . '/public')) {
            rmdir($tmp . '/public');
        }
        rmdir($tmp);

        $this->assertCount(2, $logs);
        $this->assertSame('stores', $logs[0]->getImportType());
        $this->assertSame('brochures', $logs[1]->getImportType());
        $this->assertSame(42, $logs[0]->getIprotoId());
    }

    public function testCrawlHandlesStringDates(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $shopfullyService = $this->createMock(ShopfullyService::class);
        $iprotoService = $this->createMock(IprotoService::class);

        $brochureData = [
            'publicationData' => ['data' => [['Publication' => ['pdf_url' => 'http://example.com/p.pdf']]]],
            'brochureData' => ['data' => [['Flyer' => ['id' => 'B1', 'title' => 'Title', 'stores' => '1']]]],
            'brochureStores' => [
                ['Store' => ['id' => '1', 'city' => 'Berlin', 'zip' => '10115', 'address' => 'S', 'lat' => '1', 'lng' => '2', 'more_info' => 'T', 'description' => 'D', 'phone' => 'p', 'fax' => 'f']]
            ]
        ];

        $shopfullyService->expects($this->once())
            ->method('getBrochure')
            ->with('123', 'de_de')
            ->willReturn($brochureData);

        $iprotoService->expects($this->exactly(2))
            ->method('importData')
            ->willReturn(['@id' => '/imports/9', 'status' => 'ok']);

        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->exactly(2))->method('flush');

        $crawler = new ShopfullyCrawler($em, $shopfullyService, $iprotoService);

        $data = [
            'company' => 42,
            'locale' => 'de_de',
            'numbers' => [[
                'number' => '123',
                'tracking_pixel' => 'pix',
                'validity_start' => '2023-01-01',
                'validity_end' => '2023-01-02',
                'visibility_start' => '2023-01-01',
            ]]
        ];

        $cwd = getcwd();
        $tmp = sys_get_temp_dir() . '/shopfully_test_' . uniqid();
        mkdir($tmp);
        chdir($tmp);

        $crawler->crawl($data);

        chdir($cwd);
        foreach (glob($tmp . '/public/csv/*.csv') as $file) {
            unlink($file);
        }
        if (is_dir($tmp . '/public/csv')) {
            rmdir($tmp . '/public/csv');
        }
        if (is_dir($tmp . '/public')) {
            rmdir($tmp . '/public');
        }
        rmdir($tmp);

        $this->assertTrue(true); // ensure no exception
    }

    public function testCrawlHandlesMissingDates(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $shopfullyService = $this->createMock(ShopfullyService::class);
        $iprotoService = $this->createMock(IprotoService::class);

        $brochureData = [
            'publicationData' => ['data' => [['Publication' => ['pdf_url' => 'http://example.com/p.pdf']]]],
            'brochureData' => ['data' => [['Flyer' => ['id' => 'B1', 'title' => 'Title', 'stores' => '1']]]],
            'brochureStores' => [
                ['Store' => ['id' => '1', 'city' => 'Berlin', 'zip' => '10115', 'address' => 'S', 'lat' => '1', 'lng' => '2', 'more_info' => 'T', 'description' => 'D', 'phone' => 'p', 'fax' => 'f']]
            ]
        ];

        $shopfullyService->expects($this->once())
            ->method('getBrochure')
            ->with('123', 'de_de')
            ->willReturn($brochureData);

        $iprotoService->expects($this->exactly(2))
            ->method('importData')
            ->willReturn(['@id' => '/imports/9', 'status' => 'ok']);

        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->exactly(2))->method('flush');

        $crawler = new ShopfullyCrawler($em, $shopfullyService, $iprotoService);

        $data = [
            'company' => 42,
            'locale' => 'de_de',
            'numbers' => [[
                'number' => '123',
                'tracking_pixel' => 'pix',
                'validity_start' => null,
                'validity_end' => null,
                'visibility_start' => null,
            ]]
        ];

        $cwd = getcwd();
        $tmp = sys_get_temp_dir() . '/shopfully_test_' . uniqid();
        mkdir($tmp);
        chdir($tmp);

        $crawler->crawl($data);

        chdir($cwd);
        foreach (glob($tmp . '/public/csv/*.csv') as $file) {
            unlink($file);
        }
        if (is_dir($tmp . '/public/csv')) {
            rmdir($tmp . '/public/csv');
        }
        if (is_dir($tmp . '/public')) {
            rmdir($tmp . '/public');
        }
        rmdir($tmp);

        $this->assertTrue(true); // ensure no exception
    }
}
