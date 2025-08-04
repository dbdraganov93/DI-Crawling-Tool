<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\ClickoutsMapperService;
use App\Service\PdfLinkAnnotatorService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\PdfDownloaderService;
use App\Service\S3Service;

class ShopfullyService
{
    private const SHOPFULLY_HOST = 'https://d1h08qwp2t1dnu.cloudfront.net/v1/';
    private const API_KEY = 'e1515941-38d4-4ecf-b5fb-3970adbefb1d';
    private HttpClientInterface $httpClient;
    private ClickoutsMapperService $clickoutsMapperService;

    private PdfDownloaderService $pdfDownloaderService;
    private S3Service $s3Service;
    private PdfLinkAnnotatorService $pdfLinkAnnotatorService;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        ClickoutsMapperService $clickoutsMapperService,
        PdfDownloaderService $pdfDownloaderService,
        PdfLinkAnnotatorService $pdfLinkAnnotatorService,
        S3Service $s3Service,
        LoggerInterface $logger,
    ) {
        $this->httpClient = $httpClient;
        $this->clickoutsMapperService = $clickoutsMapperService;
        $this->pdfDownloaderService = $pdfDownloaderService;
        $this->pdfLinkAnnotatorService = $pdfLinkAnnotatorService;
        $this->s3Service = $s3Service;
        $this->logger = $logger;
    }

    private function getBrochureStoresAsString(array $stores): string
    {
        $storesString = '';
        foreach ($stores as $store) {
            $storesString .= $store['Store']['id'] . ',';
        }

        return rtrim($storesString, ',');
    }

    public function getBrochure(string $brochureId, string $locale): array
    {
        $response['brochureData'] = $this->fetchBrochureData($brochureId, $locale);
        $response['publicationData'] = $this->fetchPublicationData($brochureId, $locale);
        $response['brochureStores'] = $this->fetchStoresByBrochureId($brochureId, $locale);
        $response['brochureData']['stores'] = $this->getBrochureStoresAsString($response['brochureStores']);
        $response['brochureClickouts'] = $this->fetchBrochureClickouts($brochureId, $locale);
        $response['clickoutsCount'] = count($response['brochureClickouts']);

        try {
            $response['brochureData']['pdf_url'] = $this->pdfDownloaderService->download(
                $response['publicationData']['pdf_url'],
                self::API_KEY
            );
        } catch (\Exception $e) {
            $this->logger->error('Download failed: ' . $e->getMessage());
        }

        if (!empty($response['brochureData']['pdf_url'])) {
            $this->pdfLinkAnnotatorService->annotate(
                $response['brochureData']['pdf_url'],
                $response['brochureData']['pdf_url'],
                $response['brochureClickouts']
            );
            $response['brochureData']['pdf_url'] = $this->s3Service->upload($response['brochureData']['pdf_url']);
        }

        return $response;
    }

    public function fetchStoresByBrochureId(string $brochureId, string $locale): array
    {
        $allStores = [];
        $page = 1;

        while (true) {
            $url = self::SHOPFULLY_HOST . $locale . '/flyers/' . $brochureId . '/stores.json?page=' . $page;

            try {
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => [
                        'x-api-key' => self::API_KEY,
                    ],
                ]);

                $data = $response->toArray();
                $stores = $data['data'] ?? [];

                if (empty($stores)) {
                    break;
                }

                $allStores = array_merge($allStores, $stores);
                $page++;
            } catch (ClientExceptionInterface $e) {
                if (method_exists($e, 'getResponse') && $e->getResponse()->getStatusCode() === 404) {
                    break; // end of pages
                }
                throw $e;
            }
        }

        return $allStores;
    }



    public function fetchBrochureClickouts(string $brochureId, string $locale): array
    {

        $url = self::SHOPFULLY_HOST . $locale . '/flyers/' . $brochureId . '/flyer_gibs.json';

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'x-api-key' => self::API_KEY,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to fetch clickout data fore brochure: ' . $brochureId . ', status code: ' . $response->getStatusCode());
        }
        $response = $response->toArray();
        return $this->clickoutsMapperService->formatClickoutsForShopfully($response);
    }

    public function fetchBrochureData(string $brochureId, string $locale): array
    {
        $url = self::SHOPFULLY_HOST . $locale . '/flyers/' . $brochureId . '.json';

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'x-api-key' => self::API_KEY,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to fetch brochure data');
        }
        $arrayResponse = $response->toArray();

        return $arrayResponse['data'][0]['Flyer'] ?? [];
    }

    public function fetchPublicationData(string $brochureId, string $locale = 'it_it'): array
    {
        $url = self::SHOPFULLY_HOST . $locale . '/flyers/' . $brochureId . '/publications.json?modifiers=pdf_url';

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'x-api-key' => self::API_KEY,
                'Accept' => '*/*',
                'Connection' => 'keep-alive',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to fetch flyer data');
        }

        $arrayResponse = $response->toArray();

        return $arrayResponse['data'][0]['Publication'] ?? [];
    }
}
