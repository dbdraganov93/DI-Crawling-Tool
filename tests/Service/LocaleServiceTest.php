<?php

namespace App\Tests\Service;

use App\Service\LocaleService;
use PHPUnit\Framework\TestCase;

class LocaleServiceTest extends TestCase
{
    public function testGetLocaleAndOwnerId(): void
    {
        $service = new LocaleService();
        $this->assertSame('it_it', $service->getLocale(231));
        $this->assertSame('', $service->getLocale(999));
        $this->assertSame(231, $service->getOwnerId('it_it'));
        $this->assertNull($service->getOwnerId('unknown'));
    }
}
