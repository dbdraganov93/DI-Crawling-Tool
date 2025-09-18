<?php

declare(strict_types=1);

namespace App\Tests\Service\Flipify;

use App\Service\Flipify\OpenAiProductExtractor;
use App\Service\Flipify\PdfImageSet;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenAiProductExtractorTest extends TestCase
{
    public function testExtractFromImagesParsesStructuredJsonResponse(): void
    {
        $tempDirectory = sys_get_temp_dir() . '/flipify_test_' . uniqid('', true);
        mkdir($tempDirectory, 0775, true);
        $imagePath = $tempDirectory . '/page-001.png';
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==', true));

        $pdfImageSet = new PdfImageSet($tempDirectory, [$imagePath]);

        $capturedPayload = null;
        $mockResponseBody = json_encode([
            'choices' => [[
                'message' => [
                    'content' => [[
                        'type' => 'output_json',
                        'text' => json_encode([
                            'products' => [[
                                'page' => 1,
                                'name' => 'Sample Product',
                                'category' => 'Snacks',
                                'price' => 4.99,
                                'discount_price' => 3.49,
                                'currency' => 'EUR',
                                'position' => 2,
                                'bounding_box' => [
                                    'x' => 12.3,
                                    'y' => 45.6,
                                    'width' => 78.9,
                                    'height' => 10.1,
                                ],
                                'description' => 'Tasty treat',
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ],
            ]],
        ], JSON_THROW_ON_ERROR);

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedPayload, $mockResponseBody): MockResponse {
            if (isset($options['json'])) {
                $capturedPayload = $options['json'];
            } elseif (isset($options['body'])) {
                $capturedPayload = json_decode((string) $options['body'], true, 512, JSON_THROW_ON_ERROR);
            }

            return new MockResponse($mockResponseBody, [
                'http_code' => 200,
            ]);
        });

        $extractor = new OpenAiProductExtractor($httpClient, 'test-key', new NullLogger());
        $products = $extractor->extractFromImages($pdfImageSet);

        self::assertNotNull($capturedPayload);
        self::assertSame('gpt-4o-mini', $capturedPayload['model']);
        self::assertCount(1, $products);
        self::assertSame(1, $products[0]['page']);
        self::assertSame('Sample Product', $products[0]['name']);
        self::assertSame('Snacks', $products[0]['category']);
        self::assertSame(4.99, $products[0]['price']);
        self::assertSame(3.49, $products[0]['discount_price']);
        self::assertSame('EUR', $products[0]['currency']);
        self::assertSame(2, $products[0]['position']);
        self::assertSame([
            'x' => 12.3,
            'y' => 45.6,
            'width' => 78.9,
            'height' => 10.1,
        ], $products[0]['bounding_box']);
        self::assertSame('Tasty treat', $products[0]['description']);

        $pdfImageSet->cleanup();
    }
}
