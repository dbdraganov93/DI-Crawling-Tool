<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IprotoService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private IprotoTokenService $tokenService,
    ) {}

    public function findStoresByCompany(int $companyId, bool $visibleOnly = true): array
    {
        $stores = [];
        $page = 1;
        $pageSize = 100;

        do {
            $params = [
                'integration' => '/api/integrations/' . $companyId,
                'page' => $page++,
                'itemsPerPage' => $pageSize,
            ];

            if ($visibleOnly) {
                $params['exists'] = ['deletedAt' => false];
            }

            $response = $this->sendRequest('GET', '/api/stores', $params);

            if (!isset($response['body']['hydra:member'])) {
                var_dump($response);
                throw new \RuntimeException('Invalid response from iProto');
            }

            foreach ($response['body']['hydra:member'] as $store) {
                $stores[] = $store; // You can call mapStoreToApi3() here if needed
            }
        } while (count($response['body']['hydra:member']) === $pageSize);

        return $stores;
    }

    private function sendRequest(string $method, string $uri, array $params = [], $body = null, string $bodyMediaType = 'application/ld+json'): array
    {
        $baseUrl = 'https://iproto.offerista.com';//'https://iproto.public-elb.di-vostok.offerista.com';
        $token = $this->tokenService->getValidToken();

        if (!$token) {
            throw new \RuntimeException('No valid token found');
        }

        // Нормализиране и build на query string
        $normalizedParams = $this->normalizeParams($params);
        $query = count($normalizedParams) > 0 ? '?' . http_build_query($normalizedParams) : '';

        $url = rtrim($baseUrl, '/') . '/' . ltrim($uri, '/') . $query;

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

        // DEBUG: покажи какъв е URL и headers (само по време на разработка)
        $this->logger->info("Request: \"$method $url\"");
        $this->logger->info("Headers: " . json_encode($headers));
        if ($body) {
            $this->logger->info("Body: " . (is_array($body) ? json_encode($body) : $body));
        }

        $attempt = 0;
        $maxAttempts = 5;

        do {
            try {
                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();
                $this->logger->info("Response: \"$statusCode $url\"");

                $content = $response->getContent(false); // взимаш и при 401, без throw
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
        } while ($attempt < $maxAttempts);

        throw new \RuntimeException("iProto API request failed after $maxAttempts attempts: $method $url");
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
