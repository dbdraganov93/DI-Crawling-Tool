<?php

namespace App\Tests\Service;

use App\Service\CountryTimezoneResolver;
use PHPUnit\Framework\TestCase;

class CountryTimezoneResolverTest extends TestCase
{
    public function testGetTimezoneForCountry(): void
    {
        $resolver = new CountryTimezoneResolver();
        $timezone = $resolver->getTimezoneForCountry('DE');
        $this->assertSame('Europe/Berlin', $timezone);
        $this->assertNull($resolver->getTimezoneForCountry('XX'));
    }

    public function testResolveFromApiPath(): void
    {
        $resolver = new CountryTimezoneResolver();
        $this->assertSame('Europe/Berlin', $resolver->resolveFromApiPath('/api/countries/DE'));
        $this->assertNull($resolver->resolveFromApiPath('/foo/bar'));
    }
}
