<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IprotoService
{
    private const BASE_URL = 'https://iproto.offerista.com';
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private IprotoTokenService $tokenService,
    ) {}

    public function getAllCompanies(string $owner, int $itemsPerPage = 1000)
    {
        $response = $this->sendRequest('GET', '/api/integrations', ['itemsPerPage' => $itemsPerPage, 'owner' => $owner]);

        return $response['body'];
    }

    public function getAllOwners()
    {
        $response = $this->sendRequest('GET', '/api/owners');

        return $response['body'];
    }

    public function findStoresByCompany(int $companyId, bool $visibleOnly = true): array
    {
        $stores = [];
        $pageSize = 100;
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $params = $this->buildStoreQueryParams($companyId, $visibleOnly, $page, $pageSize);

            $response = $this->sendRequest('GET', '/api/stores', $params);

            $items = $response['body'] ?? null;

            if (!is_array($items)) {
                throw new \RuntimeException('Invalid response from iProto');
            }

            $stores = array_merge($stores, $items);

            $hasMore = count($items) === $pageSize;
            $page++;
        }

        return $stores;
    }

    private function buildStoreQueryParams(int $companyId, bool $visibleOnly, int $page, int $pageSize): array
    {
        $params = [
            'integration' => '/api/integrations/' . $companyId,
             'page' => $page,
             'itemsPerPage' => $pageSize,
        ];

        if ($visibleOnly) {
            $params['exists'] = ['deletedAt' => false];
        }

        return $params;
    }


    private function sendRequest(string $method, string $uri, array $params = [], $body = null, string $bodyMediaType = 'application/ld+json'): array
    {
        $token = $this->tokenService->getValidToken();
        if (!$token) {
            throw new \RuntimeException('No valid token found');
        }

        $url = $this->buildUrl($uri, $params);

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
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
}
