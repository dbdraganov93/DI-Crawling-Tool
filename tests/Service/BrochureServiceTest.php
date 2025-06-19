<?php

namespace App\Tests\Service;

use App\Service\BrochureService;
use PHPUnit\Framework\TestCase;

class BrochureServiceTest extends TestCase
{
    public function testAddCurrentBrochureCollectsData(): void
    {
        $service = new BrochureService(42);
        $service->setPdfUrl('http://example.com/file.pdf')
            ->setSalesRegion('north')
            ->setBrochureNumber('BR1')
            ->setTitle('My brochure')
            ->setVariety('special')
            ->setValidFrom('2023-01-01')
            ->setValidTo('2023-01-31')
            ->setVisibleFrom('2022-12-25')
            ->setLayout('default')
            ->setStoreNumber('100')
            ->setPdfProcessingOptions(['dpi' => 300])
            ->setTrackingPixels('pixel')
            ->addCurrentBrochure();

        $brochures = $service->getBrochures();
        $this->assertCount(1, $brochures);
        $brochure = $brochures[0];
        $this->assertSame('http://example.com/file.pdf', $brochure['pdfUrl']);
        $this->assertSame('https://iproto.offerista.com/api/integrations/42', $brochure['integration']);
        $this->assertSame('north', $brochure['sales_region']);
        $this->assertSame('BR1', $brochure['brochureNumber']);
        $this->assertSame('My brochure', $brochure['title']);
        $this->assertSame('special', $brochure['variety']);
        $this->assertSame('2023-01-01', $brochure['validFrom']);
        $this->assertSame('2023-01-31', $brochure['validTo']);
        $this->assertSame('100', $brochure['storeNumber']);
        $this->assertSame('2022-12-25', $brochure['visibleFrom']);
        $this->assertSame(['dpi' => 300], $brochure['pdfProcessingOptions']);
        $this->assertSame('pixel', $brochure['trackingPixels']);
    }

    public function testDefaultVarietyIsUsed(): void
    {
        $service = new BrochureService(1);
        $service->setPdfUrl('url')->addCurrentBrochure();
        $brochures = $service->getBrochures();
        $this->assertSame('leaflet', $brochures[0]['variety']);
    }
}
