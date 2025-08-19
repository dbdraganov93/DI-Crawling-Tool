<?php

namespace App\Tests\Service;

use App\Service\BrochureLinkerService;
use PHPUnit\Framework\TestCase;

class BrochureLinkerServiceNormalizeProductTest extends TestCase
{
    private function invokeNormalize(mixed $item): ?array
    {
        $ref = new \ReflectionClass(BrochureLinkerService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('normalizeProduct');
        $method->setAccessible(true);
        return $method->invoke($svc, $item, 1, []);
    }

    public function testHandlesStringProduct(): void
    {
        $result = $this->invokeNormalize('Milk');
        $this->assertNotNull($result);
        $this->assertSame('Milk', $result['product']);
        $this->assertSame(1, $result['page']);
    }
}
