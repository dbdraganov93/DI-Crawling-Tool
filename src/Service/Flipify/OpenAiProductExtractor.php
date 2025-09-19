<?php

namespace App\Service\Flipify;

use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiProductExtractor
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'OPENAI_API_KEY')]
        private readonly string $openAiApiKey,
        #[Autowire(service: 'monolog.logger.flipify')]
        private readonly LoggerInterface $logger,
    ) {
        if ($this->openAiApiKey === '') {
            throw new RuntimeException('The OpenAI API key is not configured.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extractFromImages(PdfImageSet $imageSet): array
    {
        $paths = $imageSet->getPaths();
        $pageCount = \count($paths);

        $this->logger->info('Submitting brochure pages to OpenAI.', [
            'pageCount' => $pageCount,
        ]);

        $products = [];

        foreach ($paths as $index => $path) {
            $pageNumber = $index + 1;
            $this->logger->debug('Calling OpenAI for brochure page.', [
                'page' => $pageNumber,
                'imagePath' => $path,
            ]);

            $pageProducts = $this->analyseSinglePage($path, $pageNumber);

            $this->logger->info('OpenAI returned products for brochure page.', [
                'page' => $pageNumber,
                'productCount' => \count($pageProducts),
            ]);

            foreach ($pageProducts as $product) {
                $products[] = $product;
            }
        }

        $this->logger->info('Completed OpenAI extraction for brochure.', [
            'totalProducts' => \count($products),
        ]);

        return $products;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function analyseSinglePage(string $imagePath, int $pageNumber): array
    {
        $imageContents = @file_get_contents($imagePath);
        if ($imageContents === false) {
            throw new RuntimeException(sprintf('Unable to read generated image "%s".', $imagePath));
        }

        $base64Image = base64_encode($imageContents);
        if ($base64Image === '') {
            $this->logger->warning('Generated brochure image was empty.', ['image' => $imagePath, 'page' => $pageNumber]);

            return [];
        }

        $sizeInfo = @getimagesize($imagePath);
        $width = $sizeInfo[0] ?? null;
        $height = $sizeInfo[1] ?? null;

        $pageContext = sprintf(
            'Analyse page %d of a promotional brochure image%s. Identify every distinct product that a shopper could buy.',
            $pageNumber,
            $width && $height ? sprintf(' (%dpx wide by %dpx tall)', $width, $height) : ''
        );

        $prompt = $pageContext
            . ' For every detected product return an object in the products array. '
            . 'Populate the page field with the current page number.'
            . ' If a detail is not visible use null. Use the provided schema exactly. '
            . 'Coordinates must describe the product bounding box in pixels measured from the top-left corner of the page image.';

        $messages = [
            [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'You are a meticulous retail analyst. Always answer using valid JSON that matches the provided schema. '
                            . 'Capture product names exactly as written (normalise whitespace), detect prices and discount prices as decimal numbers,'
                            . ' identify the currency code, and determine the reading order position from top-left to bottom-right.',
                    ],
                ],
            ],
            [
                'role' => 'user',
                'content' => array_values(array_filter([
                    [
                        'type' => 'text',
                        'text' => $prompt . ' Return JSON only.',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => sprintf('data:image/png;base64,%s', $base64Image),
                            'detail' => 'high',
                        ],
                    ],
                ])),
            ],
        ];

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'products' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'page' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'category' => ['type' => ['string', 'null']],
                            'price' => ['type' => ['number', 'null']],
                            'discount_price' => ['type' => ['number', 'null']],
                            'currency' => ['type' => ['string', 'null']],
                            'position' => ['type' => 'integer'],
                            'bounding_box' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'x' => ['type' => ['number', 'null']],
                                    'y' => ['type' => ['number', 'null']],
                                    'width' => ['type' => ['number', 'null']],
                                    'height' => ['type' => ['number', 'null']],
                                ],
                                'required' => ['x', 'y', 'width', 'height'],
                            ],
                            'description' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['page', 'name', 'position', 'bounding_box'],
                    ],
                ],
            ],
            'required' => ['products'],
        ];

        $response = $this->httpClient->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $this->openAiApiKey),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4o-mini',
                'temperature' => 0,
                'messages' => $messages,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'flipify_products',
                        'schema' => $schema,
                    ],
                ],
            ],
            'timeout' => 180,
        ]);

        $statusCode = $response->getStatusCode();
        $rawBody = $response->getContent(false);

        if ($statusCode >= 400) {
            $this->logger->error('OpenAI API returned an error for Flipify analysis.', [
                'status' => $statusCode,
                'body' => $rawBody,
                'page' => $pageNumber,
            ]);

            throw new RuntimeException('OpenAI API error while analysing the brochure.');
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->logger->error('Failed to decode OpenAI JSON response.', [
                'page' => $pageNumber,
                'error' => $exception->getMessage(),
            ]);
            throw new RuntimeException('Failed to decode the OpenAI API response.', 0, $exception);
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;
        $jsonPayload = $this->extractJsonString($content);

        try {
            $structured = json_decode($jsonPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->logger->error('OpenAI returned malformed JSON payload.', [
                'page' => $pageNumber,
                'error' => $exception->getMessage(),
            ]);
            throw new RuntimeException('The JSON returned by OpenAI could not be parsed.', 0, $exception);
        }

        $products = $structured['products'] ?? [];
        if (!\is_array($products)) {
            return [];
        }

        $normalised = [];
        foreach ($products as $product) {
            if (!\is_array($product)) {
                continue;
            }

            $normalised[] = [
                'page' => isset($product['page']) ? (int) $product['page'] : $pageNumber,
                'name' => isset($product['name']) ? trim((string) $product['name']) : 'Unknown product',
                'category' => isset($product['category']) ? $this->normaliseString($product['category']) : null,
                'price' => $this->normaliseNumeric($product['price'] ?? null),
                'discount_price' => $this->normaliseNumeric($product['discount_price'] ?? null),
                'currency' => isset($product['currency']) ? $this->normaliseString($product['currency']) : null,
                'position' => isset($product['position']) ? (int) $product['position'] : null,
                'bounding_box' => $this->normaliseBoundingBox($product['bounding_box'] ?? []),
                'description' => isset($product['description']) ? $this->normaliseString($product['description']) : null,
            ];
        }

        return $normalised;
    }

    /**
     * @param mixed $content
     */
    private function extractJsonString(mixed $content): string
    {
        if (\is_string($content)) {
            return $content;
        }

        if (\is_array($content)) {
            foreach ($content as $chunk) {
                if (!\is_array($chunk)) {
                    continue;
                }

                if (($chunk['type'] ?? null) === 'output_json' && isset($chunk['text'])) {
                    return (string) $chunk['text'];
                }

                if (($chunk['type'] ?? null) === 'text' && isset($chunk['text'])) {
                    return (string) $chunk['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI response did not contain a JSON payload.');
    }

    /**
     * @param array<string, mixed> $box
     *
     * @return array{ x: ?float, y: ?float, width: ?float, height: ?float }
     */
    private function normaliseBoundingBox(array $box): array
    {
        return [
            'x' => $this->normaliseNumeric($box['x'] ?? null),
            'y' => $this->normaliseNumeric($box['y'] ?? null),
            'width' => $this->normaliseNumeric($box['width'] ?? null),
            'height' => $this->normaliseNumeric($box['height'] ?? null),
        ];
    }

    /**
     * @param mixed $value
     */
    private function normaliseNumeric(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (\is_numeric($value)) {
            return (float) $value;
        }

        if (\is_string($value)) {
            $filtered = preg_replace('/[^0-9\\.,-]/', '', $value);
            if ($filtered === null || $filtered === '') {
                return null;
            }

            $filtered = str_replace(',', '.', $filtered);

            return is_numeric($filtered) ? (float) $filtered : null;
        }

        return null;
    }

    private function normaliseString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
