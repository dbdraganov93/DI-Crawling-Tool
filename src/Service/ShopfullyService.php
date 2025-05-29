<?php

namespace App\Service;
use App\Service\ClickoutsMapperService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use App\Service\PdfDownloaderService;
class ShopfullyService
{
    private const SHOPFULLY_HOST = 'https://d1h08qwp2t1dnu.cloudfront.net/v1/';
    private const API_KEY = 'e1515941-38d4-4ecf-b5fb-3970adbefb1d';
    private HttpClientInterface $httpClient;
    private ClickoutsMapperService  $clickoutsMapperService;

    private PdfDownloaderService $pdfDownloaderService;

    public function __construct(HttpClientInterface $httpClient, ClickoutsMapperService $clickoutsMapperService, PdfDownloaderService $pdfDownloaderService,)
    {
        $this->httpClient = $httpClient;
        $this->clickoutsMapperService = $clickoutsMapperService;
        $this->pdfDownloaderService = $pdfDownloaderService;
    }

    public function getBrochure(string $brochureId, string $locale): array
    {
        $response['brochureData'] = $this->fetchBrochureData($brochureId, $locale);
        $response['publicationData'] = $this->fetchPublicationData($response['brochureData']['publication_id'], $locale);
        $response['brochureStores'] = $this->fetchStoresByBrochureId($brochureId, $locale);
        $response['brochureClickouts'] = $this->fetchBrochureClickouts($brochureId, $locale);

        try {
            $response['brochureData']['data'][0]['Publication']['pdf_local'] = $this->pdfDownloaderService->download($response['publicationData']['data'][0]['Publication']['pdf_url']);
        } catch (\Exception $e) {
            echo "Download failed: " . $e->getMessage() . "\n";
        }

        return $response;
    }

    public function fetchStoresByBrochureId(string $brochureId, string $locale): array
    {
        $url = self::SHOPFULLY_HOST . $locale . '/flyers/'.$brochureId.'/stores.json?page=1';

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'x-api-key' => self::API_KEY,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to fetch store data fore brochure: ' . $brochureId . ', status code: ' . $response->getStatusCode());
        }
        $response = $response->toArray();

        return $response['data'];
    }

    public function fetchBrochureClickouts(string $brochureId, string $locale): array
    {

        $url = self::SHOPFULLY_HOST.$locale.'/flyers/'.$brochureId.'/flyer_gibs.json';

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'x-api-key' => self::API_KEY,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to fetch clickout data fore brochure: ' . $brochureId . ', status code: ' . $response->getStatusCode());
        }
        $response = $response->toArray();
        ;
        return $this->clickoutsMapperService->formatClickoutsForShopfully($response);
    }

    public function fetchBrochureData(string $brochureId, string $locale): array
    {
        $url = self::SHOPFULLY_HOST. $locale .'/flyers/'. $brochureId .'.json';

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'x-api-key' => self::API_KEY,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to fetch brochure data');
        }
        $arrayResponse = $response->toArray();
        $publicationArray = explode('_', $arrayResponse['data'][0]['Flyer']['publication_url']);
        $arrayResponse['publication_id'] = end($publicationArray);
        return $arrayResponse;
    }

    public function fetchPublicationData(int $brochureId, string $locale = 'it_it'): array
    {
        $url = self::SHOPFULLY_HOST.$locale.'/publications/'.$brochureId.'.json?modifiers=pdf_url';


        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'x-api-key' => self::API_KEY,
                'Accept' => '*/*',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to fetch flyer data');
        }

        return $response->toArray();
    }
}