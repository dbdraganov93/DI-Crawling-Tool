<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BookingManagementService
{
    private const DEFAULT_TIME_CONSTRAINT = 'PRESENT';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[\SensitiveParameter] private string $bookingManagementBaseUrl,
        #[\SensitiveParameter] private string $bookingManagementUsername,
        #[\SensitiveParameter] private string $bookingManagementPassword,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCpcBookings(string $customerId, ?string $timeConstraint = null, ?string $ownerId = null): array
    {
        $customerId = trim($customerId);

        if ($customerId == '') {
            throw new \InvalidArgumentException('The companyId parameter is required.');
        }

        $baseUrl = rtrim($this->bookingManagementBaseUrl, '/');

        if ($baseUrl === '') {
            throw new \RuntimeException('The booking management API base URL is not configured.');
        }

        if ($this->bookingManagementUsername === '' || $this->bookingManagementPassword === '') {
            throw new \RuntimeException('Booking management API credentials are not configured.');
        }

        $query = [
            'customerId' => $customerId,
            'timeConstraint' => $timeConstraint ?? self::DEFAULT_TIME_CONSTRAINT,
        ];

        $headers = [
            'Accept' => 'application/json',
        ];

        if ($ownerId !== null) {
            $ownerId = trim($ownerId);
            if ($ownerId !== '') {
                $headers['X-Owner-Id'] = $ownerId;
            }
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                $baseUrl . '/api/management/v2/bookings/cpc',
                [
                    'headers' => $headers,
                    'query' => $query,
                    'auth_basic' => [
                        $this->bookingManagementUsername,
                        $this->bookingManagementPassword,
                    ],
                ],
            );

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->warning(
                    sprintf('Booking API returned unexpected status %d: %s', $statusCode, $content),
                );

                throw new \RuntimeException(sprintf('Booking API request failed with status %d.', $statusCode));
            }

            $decoded = json_decode($content, true);

            if (!is_array($decoded)) {
                throw new \RuntimeException('Unexpected response format received from the booking API.');
            }

            return $decoded;
        } catch (\Throwable $exception) {
            if (!$exception instanceof \RuntimeException) {
                $this->logger->error('Failed to query booking API: ' . $exception->getMessage(), [
                    'exception' => $exception,
                ]);
            }

            throw $exception instanceof \RuntimeException
                ? $exception
                : new \RuntimeException('Unable to fetch bookings from management API.', 0, $exception);
        }
    }
}

