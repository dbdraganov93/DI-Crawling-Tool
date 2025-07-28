<?php

namespace App\Tests\Service;

use App\Service\BrochureLinkerService;
use PHPUnit\Framework\TestCase;

class BrochureLinkerServicePositionTest extends TestCase
{
    private function invokeFindPosition(array $blocks, string $product): ?array
    {
        $ref = new \ReflectionClass(BrochureLinkerService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('findPosition');
        $method->setAccessible(true);
        return $method->invoke($svc, $blocks, $product);
    }

    public function testFindsPartialMatch(): void
    {
        $blocks = [
            ['text' => 'Coca-Cola 330ml', 'x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.1],
            ['text' => 'Other', 'x' => 0.5, 'y' => 0.5, 'width' => 0.2, 'height' => 0.1],
        ];
        $pos = $this->invokeFindPosition($blocks, 'Coca Cola');
        $this->assertNotNull($pos);
        $this->assertSame(0.1, $pos['x']);
    }

    public function testFindsHyphenatedWord(): void
    {
        $blocks = [
            ['text' => 'Extra-Long Sausage', 'x' => 0.2, 'y' => 0.3, 'width' => 0.1, 'height' => 0.1],
        ];
        $pos = $this->invokeFindPosition($blocks, 'Extra Long Sausage');
        $this->assertNotNull($pos);
        $this->assertSame(0.2, $pos['x']);
    }
}
