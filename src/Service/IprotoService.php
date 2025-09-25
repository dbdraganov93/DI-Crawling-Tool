<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IprotoService
{
    //stage: https://iproto.public-elb.di-stage.offerista.com
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private IprotoTokenService $tokenService,
        private string $iprotoBaseUrl = 'https://iproto.offerista.com',
    ) {
        $this->iprotoBaseUrl = rtrim($this->iprotoBaseUrl, '/');

        if ($this->iprotoBaseUrl === '') {
            throw new \InvalidArgumentException('The iProto base URL must not be empty.');
        }
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
     * @return array<int, array<string, mixed>>
     */
    public function getBrochuresByOwnerAndCompany(string $ownerId, string $companyId, int $itemsPerPage = 100): array
    {
        $ownerId = trim($ownerId);
        $companyId = trim($companyId);

        if ($ownerId === '' || $companyId === '') {
            throw new \InvalidArgumentException('Owner ID and company ID are required to fetch brochures.');
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
                'owner' => $ownerId,
                'integration' => $integrationIri,
                'integration.id' => $integrationId,
                'itemsPerPage' => $itemsPerPage,
                'page' => $page,
                'exists' => [
                    'deletedAt' => false,
                ],
                'order' => [
                    'id' => 'asc',
                    'title' => 'asc',
                    'brochureNumber' => 'asc',
                    'validFrom' => 'asc',
                    'visibleFrom' => 'asc',
                    'validTo' => 'asc',
                ],
            ];

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
        return $this->iprotoBaseUrl . '/' . ltrim($uri, '/') . $query;
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

        return $response['body'];
    }

    public function getImportStatus($importId): array
    {
        $response = $this->sendRequest('GET', '/api/imports/' . $importId);

        return $response['body'];
    }
}
