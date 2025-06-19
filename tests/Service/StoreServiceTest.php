<?php

namespace App\Tests\Service;

use App\Service\StoreService;
use PHPUnit\Framework\TestCase;

class StoreServiceTest extends TestCase
{
    public function testAddCurrentStoreCollectsData(): void
    {
        $service = new StoreService(5);
        $service->setStoreNumber('123')
            ->setCity('Berlin')
            ->setZipcode('10115')
            ->setStreet('Street')
            ->setStreetNumber('1')
            ->setLatitude('52.5')
            ->setLongitude('13.4')
            ->setTitle('Title')
            ->setSubtitle('Subtitle')
            ->setText('Desc')
            ->setPhone('555')
            ->setFax('666')
            ->setEmail('a@b.c')
            ->setStoreHours('hours')
            ->setStoreHoursNotes('notes')
            ->setPayment('cash')
            ->setWebsite('http://example.com')
            ->setDistribution('dist')
            ->setParking('park')
            ->setBarrierFree('yes')
            ->setBonusCard('bonus')
            ->setSection('section')
            ->setService('service')
            ->setToilet('yes')
            ->setDefaultRadius('20km')
            ->addCurrentStore();

        $stores = $service->getStores();
        $this->assertCount(1, $stores);
        $store = $stores[0];
        $this->assertSame('/api/integrations/5', $store['integration']);
        $this->assertSame('123', $store['storeNumber']);
        $this->assertTrue($store['barrierFree']);
        $this->assertTrue($store['customerToilet']);
        $this->assertSame(20, $store['visibilityRadius']);
    }
}
