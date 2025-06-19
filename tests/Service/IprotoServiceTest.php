<?php

namespace App\Tests\Service;

use App\Service\IprotoService;
use App\Service\IprotoTokenService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IprotoServiceTest extends TestCase
{
    private function getService(): IprotoService
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $token = $this->createMock(IprotoTokenService::class);

        return new IprotoService($httpClient, $logger, $token);
    }

    public function testNormalizeParams(): void
    {
        $service = $this->getService();
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('normalizeParams');
        $method->setAccessible(true);

        $result = $method->invoke($service, ['a' => true, 'b' => false, 'c' => ['d' => true]]);
        $this->assertSame(['a' => 'true', 'b' => 'false', 'c' => ['d' => 'true']], $result);
    }

    public function testBuildUrl(): void
    {
        $service = $this->getService();
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('buildUrl');
        $method->setAccessible(true);

        $url = $method->invoke($service, '/api/test', ['foo' => 'bar']);
        $this->assertSame('https://iproto.offerista.com/api/test?foo=bar', $url);
    }
}
