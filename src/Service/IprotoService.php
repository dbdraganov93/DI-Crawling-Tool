<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IprotoService
{
    //stage: https://iproto.public-elb.di-stage.offerista.com
    private const BASE_URL = 'https://iproto.offerista.com';
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private IprotoTokenService $tokenService,
    ) {
    }

    public function getAllCompanies(string $owner, bool $includeDeleted = false, int $itemsPerPage = 1000): array
    {
        $params = [
            'itemsPerPage' => $itemsPerPage,
            'owner' => $owner,
            'exists' => [
                'deletedAt' => $includeDeleted,
            ],
        ];

        $response = $this->sendRequest('GET', '/api/integrations', $params);

        return $response['body'];
    }



    public function getAllOwners(): array
    {
        $response = $this->sendRequest('GET', '/api/owners');

        return $response['body'];
    }

    public function getIntegrations(array $params): array
    {
        $response = $this->sendRequest('GET', '/api/integrations', $params);

        return $response['body'];
    }

    public function createCompany(array $data): array
    {
        $response = $this->sendRequest('POST', '/api/integrations', [], $data);

        return $response['body'];
    }

    public function undeleteCompany(int|string $id): array
    {
        $response = $this->sendRequest('GET', '/api/integrations/undelete/' . $id);

        return $response['body'];
    }

    public function deleteCompany(int|string $id): array
    {
        $response = $this->sendRequest('DELETE', '/api/integrations/delete/' . $id);

        return $response['body'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProduct(int|string $productId): array
    {
        $normalizedId = trim((string) $productId);

        if ($normalizedId === '') {
            throw new InvalidArgumentException('Product ID is required to fetch a product.');
        }

        $response = $this->sendRequest(
            'GET',
            sprintf('/api/products/%s', rawurlencode($normalizedId)),
            [],
            null,
            'application/ld+json',
            'application/ld+json',
        );

        $body = $response['body'];

        if (!is_array($body)) {
            throw new \RuntimeException(sprintf('Unexpected response while fetching product "%s" from iProto.', $normalizedId));
        }

        return $body;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findProductsByNumber(int|string $companyId, string $articleNumber)
    {
        $normalizedCompanyId = trim((string) $companyId);
        $normalizedArticle = trim($articleNumber);

        if ($normalizedCompanyId === '' || $normalizedArticle === '') {
            throw new InvalidArgumentException('Company ID and product number are required to locate a product.');
        }

        $response = $this->sendRequest('GET', '/api/products', [
            'integration' => '/api/integrations/' . ltrim($normalizedCompanyId, '/'),
            'productNumber' => $normalizedArticle,
            'exists' => [
                'deletedAt' => false,
            ],
            'timeConstraint' => [
                'future' => true,
            ],
            'itemsPerPage' => 1,
        ])['body'];

        if (!is_array($response) || !isset($response['hydra:totalItems'])) {
            throw new \RuntimeException('Unexpected product search response received from iProto.');
        }

        if ((int) $response['hydra:totalItems'] === 0) {
            return false;
        }

        $items = $response['hydra:member'] ?? [];
        if (!is_array($items)) {
            return false;
        }

        $first = reset($items);
        if (!is_array($first)) {
            return false;
        }

        return $this->mapProductToApi3($first);
    }


    /**
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function getBrochuresByOwnerAndCompany(string $ownerId, string $companyId, array $options = []): array
    {
        $ownerId = trim($ownerId);
        $companyId = trim($companyId);

        if ($ownerId === '' || $companyId === '') {
            throw new \InvalidArgumentException('Owner ID and company ID are required to fetch brochures.');
        }

        $itemsPerPageOption = $options['itemsPerPage'] ?? 100;
        $itemsPerPage = max(1, min((int) $itemsPerPageOption, 200));
        $integrationId = trim((string) ($this->extractIntegrationId($companyId) ?? $companyId));
        if ($integrationId === '') {
            $integrationId = $companyId;
        }
        $integrationIri = sprintf('/api/integrations/%s', ltrim($integrationId, '/'));

        $page = 1;
        $results = [];
        $remainingIterations = 200;

        $orderOptions = $options['order'] ?? ['id' => 'desc'];
        if (!is_array($orderOptions)) {
            $orderOptions = ['id' => 'desc'];
        }

        $order = [];
        foreach ($orderOptions as $field => $direction) {
            if (!is_string($field)) {
                continue;
            }

            $normalizedField = trim($field);
            if ($normalizedField === '') {
                continue;
            }

            $normalizedDirection = is_string($direction) ? strtolower(trim($direction)) : 'asc';
            $order[$normalizedField] = $normalizedDirection === 'desc' ? 'desc' : 'asc';
        }

        if (empty($order)) {
            $order = ['id' => 'desc'];
        }

        $deletedFilter = isset($options['deletedFilter']) && is_string($options['deletedFilter'])
            ? strtolower(trim($options['deletedFilter']))
            : 'active';

        $deletedExists = null;
        if ($deletedFilter === 'deleted') {
            $deletedExists = true;
        } elseif ($deletedFilter === 'active' || $deletedFilter === 'not_deleted') {
            $deletedExists = false;
        }

        $timeConstraints = ['current', 'upcoming'];
        if (array_key_exists('timeConstraints', $options)) {
            $timeConstraints = [];
            $constraintsOption = $options['timeConstraints'];

            if (is_array($constraintsOption)) {
                foreach ($constraintsOption as $constraint) {
                    if (!is_string($constraint)) {
                        continue;
                    }

                    $normalizedConstraint = strtolower(trim($constraint));
                    if ($normalizedConstraint === '') {
                        continue;
                    }

                    if (!in_array($normalizedConstraint, ['current', 'upcoming', 'past'], true)) {
                        continue;
                    }

                    if (!in_array($normalizedConstraint, $timeConstraints, true)) {
                        $timeConstraints[] = $normalizedConstraint;
                    }
                }
            }
        }

        do {
            $params = [
                'owner' => $ownerId,
                'integration' => $integrationIri,
                'integration.id' => $integrationId,
                'itemsPerPage' => $itemsPerPage,
                'page' => $page,
                'order' => $order,
            ];

            if ($deletedExists !== null) {
                $params['exists'] = [
                    'deletedAt' => $deletedExists,
                ];
            }

            if (!empty($timeConstraints)) {
                $params['timeConstraint'] = [];
                foreach ($timeConstraints as $constraint) {
                    $params['timeConstraint'][$constraint] = true;
                }
            }

            $response = $this->sendRequest(
                'GET',
                '/api/brochures',
                $params,
                null,
                'application/ld+json',
                'application/ld+json',
            );

            $data = $response['body'];

            if (!is_array($data)) {
                break;
            }

            $items = $data['hydra:member'] ?? [];
            if (!is_array($items)) {
                break;
            }

            foreach ($items as $brochure) {
                if (!is_array($brochure)) {
                    continue;
                }

                if (!$this->brochureMatchesIntegration($brochure, $integrationId)) {
                    continue;
                }

                $results[] = [
                    'id' => $brochure['id'] ?? null,
                    'brochureNumber' => $brochure['brochureNumber'] ?? null,
                    'title' => $brochure['title'] ?? null,
                    'type' => $brochure['type'] ?? null,
                    'variety' => $brochure['variety'] ?? null,
                    'languageCode' => $brochure['languageCode'] ?? null,
                    'validFrom' => $brochure['validFrom'] ?? null,
                    'validTo' => $brochure['validTo'] ?? null,
                    'visibleFrom' => $brochure['visibleFrom'] ?? null,
                ];
            }

            $nextPage = $this->extractNextPage($data);
            if ($nextPage === null || $nextPage <= $page) {
                break;
            }

            $page = $nextPage;
        } while ($remainingIterations-- > 0);

        return $results;
    }


    public function getBrochure(string $brochureId): array
    {
        $normalized = trim($brochureId);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Brochure ID is required to load brochure details.');
        }

        $uri = sprintf('/api/brochures/%s', rawurlencode($normalized));

        $response = $this->sendRequest(
            'GET',
            $uri,
            [],
            null,
            'application/ld+json',
            'application/ld+json',
        );

        $data = $response['body'];

        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Unexpected brochure response for ID "%s".', $normalized));
        }

        return $data;
    }

    public function downloadBrochurePdf(string $brochurePageId, string $destinationPath, string $brochureId): string
    {
        $pageId = trim($brochurePageId);
        $normalizedBrochureId = trim($brochureId);

        if ($pageId === '') {
            throw new InvalidArgumentException('Brochure page ID is required to download the PDF.');
        }

        if ($normalizedBrochureId === '') {
            throw new InvalidArgumentException('Brochure ID is required to download the PDF.');
        }

        $uri = sprintf('/api/stashed_files/brochures/%s', rawurlencode($normalizedBrochureId));

        $token = $this->tokenService->getValidToken();
        if ($token === '') {
            throw new \RuntimeException('Unable to download brochure PDF without a valid token.');
        }

        $url = $this->buildUrl($uri);
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/pdf',
            ],
            'http_version' => '1.1',
        ];

        try {
            $response = $this->httpClient->request('GET', $url, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->warning(sprintf(
                    'Failed to download brochure PDF %s (page %s): status %d, body: %s',
                    $normalizedBrochureId,
                    $pageId,
                    $statusCode,
                    $response->getContent(false)
                ));

                throw new \RuntimeException(sprintf(
                    'Unable to download brochure PDF %s (status %d).',
                    $normalizedBrochureId,
                    $statusCode
                ));
            }

            $content = $response->getContent();
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf('Unable to download brochure PDF %s.', $normalizedBrochureId),
                0,
                $exception
            );
        }

        $directory = dirname($destinationPath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Unable to create directory "%s" for brochure PDF.', $directory));
            }
        }

        if (file_put_contents($destinationPath, $content) === false) {
            throw new \RuntimeException(sprintf(
                'Unable to write brochure PDF %s to "%s".',
                $normalizedBrochureId,
                $destinationPath
            ));
        }

        return $destinationPath;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBrochureDetails(string $brochureId): array
    {
        $normalized = trim($brochureId);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Brochure ID is required to fetch brochure details.');
        }

        $baseParams = [
            'itemsPerPage' => 10,
            'page' => 1,
            'order' => [
                'id' => 'asc',
                'title' => 'asc',
                'brochureNumber' => 'asc',
                'validFrom' => 'asc',
                'visibleFrom' => 'asc',
                'validTo' => 'asc',
            ],
            'exists' => [
                'deletedAt' => false,
            ],
        ];

        $filters = [
            ['id' => $normalized],
            ['id[]' => $normalized],
            ['brochureNumber' => $normalized],
            ['brochureNumber[]' => $normalized],
        ];

        foreach ($filters as $filter) {
            $items = $this->requestBrochureMembers($baseParams + $filter);
            $brochure = $this->matchBrochureFromItems($items, $normalized);

            if ($brochure !== null) {
                return $brochure;
            }
        }

        try {
            return $this->getBrochure($normalized);
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf('Brochure details for ID "%s" were not found.', $normalized),
                0,
                $exception
            );
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, mixed>
     */
    private function requestBrochureMembers(array $params): array
    {
        $response = $this->sendRequest(
            'GET',
            '/api/brochures',
            $params,
            null,
            'application/ld+json',
            'application/ld+json',
        );

        $data = $response['body'];

        if (!is_array($data)) {
            return [];
        }

        $items = $data['hydra:member'] ?? [];

        return is_array($items) ? $items : [];
    }

    /**
     * @param array<int, mixed> $items
     */
    private function matchBrochureFromItems(array $items, string $expectedId): ?array
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $candidateId = $item['id'] ?? null;

            if ($candidateId === null && isset($item['@id'])) {
                $candidateId = $this->extractIntegrationId($item['@id']);
            }

            if ($candidateId !== null && (string) $candidateId === $expectedId) {
                return $item;
            }

            $brochureNumber = $item['brochureNumber'] ?? $item['number'] ?? null;
            if ($brochureNumber !== null && (string) $brochureNumber === $expectedId) {
                return $item;
            }
        }

        return null;
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStoresByCompany(string $companyId, int $itemsPerPage = 30): array
    {
        $companyId = trim($companyId);

        if ($companyId === '') {
            throw new \InvalidArgumentException('Company ID is required to fetch stores.');
        }

        $itemsPerPage = max(1, min($itemsPerPage, 200));
        $integrationId = trim((string) ($this->extractIntegrationId($companyId) ?? $companyId));

        if ($integrationId === '') {
            $integrationId = $companyId;
        }

        $integrationIri = sprintf('/api/integrations/%s', ltrim($integrationId, '/'));

        $page = 1;
        $results = [];
        $remainingIterations = 200;

        do {
            $params = [
                'integration' => $integrationIri,
                'integration.id' => $integrationId,
                'itemsPerPage' => $itemsPerPage,
                'page' => $page,
                'order' => [
                    'id' => 'asc',
                    'title' => 'asc',
                    'storeNumber' => 'asc',
                    'postalCode' => 'asc',
                    'city' => 'asc',
                    'street' => 'asc',
                    'streetNumber' => 'asc',
                    'visibilityRadius' => 'asc',
                    'integration.title' => 'asc',
                ],
            ];

            $response = $this->sendRequest(
                'GET',
                '/api/stores',
                $params,
                null,
                'application/ld+json',
                'application/ld+json',
            );

            $data = $response['body'];

            if (!is_array($data)) {
                break;
            }

            $items = $data['hydra:member'] ?? [];
            if (!is_array($items)) {
                break;
            }

            foreach ($items as $store) {
                if (!is_array($store)) {
                    continue;
                }

                if (!$this->storeMatchesIntegration($store, $integrationId)) {
                    continue;
                }

                $results[] = $store;
            }

            $nextPage = $this->extractNextPage($data);
            if ($nextPage === null || $nextPage <= $page) {
                break;
            }

            $page = $nextPage;
        } while ($remainingIterations-- > 0);

        return $results;
    }




    private function sendRequest(string $method, string $uri, array $params = [], $body = null, string $bodyMediaType = 'application/ld+json', string $acceptType = 'application/json'): array
    {

        $token = $this->tokenService->getValidToken();
        if (!$token) {
            throw new \RuntimeException('No valid token found');
        }

        $url = $this->buildUrl($uri, $params);

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => $acceptType,
        ];

        if ($body) {
            $headers['Content-Type'] = $bodyMediaType;
        }

        $options = [
            'headers' => $headers,
            'http_version' => '1.1',
        ];

        if ($body) {
            $options['body'] = is_array($body) ? json_encode($body) : $body;
        }

        $this->logger->info("Request: \"$method $url\"");
        if ($body) {
            $this->logger->info("Body: " . (is_array($body) ? json_encode($body) : $body));
        }

        $attempt = 0;

        do {
            try {
                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                $this->logger->info("Response: \"$statusCode $url\"");

                $content = $response->getContent(false); // do not throw

                $json = json_decode($content, true);

                if ($statusCode >= 200 && $statusCode < 300) {
                    return [
                        'code' => $statusCode,
                        'body' => $json,
                    ];
                }

                $this->logger->warning("Unexpected status [$statusCode]: " . $content);
            } catch (\Exception $e) {
                $this->logger->error("Exception on request: " . $e->getMessage());
            }

            sleep(pow($attempt++, 2));
        } while ($attempt < self::MAX_ATTEMPTS);
        throw new \RuntimeException("iProto API request failed after " . self::MAX_ATTEMPTS . " attempts: $method $url");
    }

    private function buildUrl(string $uri, array $params = []): string
    {
        $normalizedParams = $this->normalizeParams($params);
        $query = count($normalizedParams) > 0 ? '?' . http_build_query($normalizedParams) : '';
        return rtrim(self::BASE_URL, '/') . '/' . ltrim($uri, '/') . $query;
    }

    private function normalizeParams(array $params): array
    {
        return array_map(function ($value) {
            if (is_array($value)) {
                return $this->normalizeParams($value);
            }
            return $value === true ? 'true' : ($value === false ? 'false' : $value);
        }, $params);
    }

    private function extractNextPage(array $data): ?int
    {
        $view = $data['hydra:view'] ?? null;
        if (!is_array($view)) {
            return null;
        }

        $next = $view['hydra:next'] ?? null;
        if (!is_string($next) || $next === '') {
            return null;
        }

        $query = parse_url($next, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $page = isset($params['page']) ? (int) $params['page'] : null;

        return $page && $page > 0 ? $page : null;
    }

    private function brochureMatchesIntegration(array $brochure, string $companyId): bool
    {
        if ($companyId === '') {
            return true;
        }

        $extracted = $this->extractIntegrationId($brochure['integration'] ?? null);

        if ($extracted === null) {
            return true;
        }

        return (string) $extracted === (string) $companyId;
    }

    private function storeMatchesIntegration(array $store, string $companyId): bool
    {
        if ($companyId === '') {
            return true;
        }

        $extracted = $this->extractIntegrationId($store['integration'] ?? null);

        if ($extracted === null) {
            return true;
        }

        return (string) $extracted === (string) $companyId;
    }

    private function extractIntegrationId(mixed $integration): ?string
    {
        if (is_array($integration)) {
            if (isset($integration['id'])) {
                return $this->extractIntegrationId($integration['id']);
            }

            if (isset($integration['@id'])) {
                return $this->extractIntegrationId($integration['@id']);
            }

            return null;
        }

        if (!is_string($integration)) {
            return null;
        }

        $value = trim($integration);
        if ($value === '') {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $value = $path;
        }

        $value = trim($value, '/');
        if ($value === '') {
            return null;
        }

        $segments = explode('/', $value);
        $lastSegment = end($segments);

        return $lastSegment !== false ? $lastSegment : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProductToApi3(array $product): array
    {
        $id = $product['id'] ?? null;

        if ($id === null && isset($product['@id'])) {
            $id = $this->extractIntegrationId($product['@id']);
        }

        if (is_numeric($id)) {
            $id = (int) $id;
        } elseif ($id !== null) {
            $id = (string) $id;
        }

        return [
            'id' => $id,
            'productNumber' => $this->normalizeProductString($product['productNumber'] ?? $product['product_number'] ?? ''),
            'title' => $this->normalizeProductString($product['title'] ?? ''),
            'description' => $this->normalizeProductString($product['description'] ?? ''),
            'price' => $this->normalizeProductString($product['price'] ?? ''),
            'currency' => $this->normalizeProductString($product['currency'] ?? ''),
            'secondaryPrice' => $this->normalizeProductString($product['secondaryPrice'] ?? ''),
            'secondaryCurrency' => $this->normalizeProductString($product['secondaryCurrency'] ?? ''),
            'manufacturerPrice' => $this->normalizeProductString($product['manufacturerPrice'] ?? ''),
            'secondaryManufacturerPrice' => $this->normalizeProductString($product['secondaryManufacturerPrice'] ?? ''),
            'manufacturerNumber' => $this->normalizeProductString($product['manufacturerNumber'] ?? ''),
            'gtin' => $this->normalizeProductString($product['gtin'] ?? ''),
            'languageCode' => $this->normalizeProductString($product['languageCode'] ?? ''),
            'keywords' => $this->normalizeProductList($product['keywords'] ?? null),
            'trackingPixels' => $this->normalizeProductList($product['trackingPixels'] ?? null),
            'url' => $this->normalizeProductString($product['url'] ?? ''),
            'brandText' => $this->normalizeProductString($product['brandText'] ?? ''),
            'brandImage' => $this->normalizeProductString($product['brandImage'] ?? ''),
            'amount' => $this->normalizeProductString($product['amount'] ?? ''),
            'size' => $this->normalizeProductString($product['size'] ?? ''),
            'color' => $this->normalizeProductString($product['color'] ?? ''),
            'unitType' => $this->normalizeProductString($product['unitType'] ?? ''),
            'subTitle' => $this->normalizeProductString($product['subTitle'] ?? ''),
            'salesRegion' => $this->normalizeProductString($product['salesRegion'] ?? ''),
            'validFrom' => $this->normalizeProductString($product['validFrom'] ?? ''),
            'validTo' => $this->normalizeProductString($product['validTo'] ?? ''),
            'visibleFrom' => $this->normalizeProductString($product['visibleFrom'] ?? ''),
            'hidden' => $product['hidden'] ?? false,
            'priceIsVariable' => $product['priceIsVariable'] ?? false,
            'additionalProperties' => $this->normalizeProductString($product['additionalProperties'] ?? ''),
            'shipping' => $this->normalizeProductString($product['shipping'] ?? ''),
            'integration' => $this->normalizeProductString($product['integration'] ?? ''),
            'variants' => is_array($product['variants'] ?? null) ? $product['variants'] : [],
            'raw' => $product,
        ];
    }

    private function normalizeProductString(mixed $value): string
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function normalizeProductList(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            $items = [];

            foreach ($value as $entry) {
                if (is_array($entry)) {
                    $entry = $entry['value'] ?? $entry['url'] ?? $entry['label'] ?? $entry['code'] ?? null;
                }

                $normalized = $this->normalizeProductString($entry);
                if ($normalized !== '') {
                    $items[] = $normalized;
                }
            }

            return implode(', ', $items);
        }

        return '';
    }

    public function importData(array $data): array
    {
        // Auto-detect if $data is in CSV result format
        if (isset($data['base64']) && isset($data['filePath']) && isset($data['downloadLink'])) {
            $data = [
                'integration' => 'api/integrations/' . $data['companyId'],
                'type' => $data['type'] . ':api3',
                'integrationOptions' => ['appendOnly' => true],
                'content' => $data['base64'],
            ];
        }

        // Now $data is in the expected format
        $response = $this->sendRequest('POST', '/api/imports', [], $data, 'application/ld+json', 'application/ld+json');
        $body = $response['body'];

        if (!is_array($body)) {
            throw new \RuntimeException('Unexpected import response received from iProto.');
        }

        return $body;
    }

    public function getImportStatus($importId): array
    {
        $response = $this->sendRequest('GET', '/api/imports/' . $importId);

        return $response['body'];
    }
}
