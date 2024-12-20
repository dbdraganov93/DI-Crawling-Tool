<?php

namespace App\Service;
use Symfony\Contracts\HttpClient\HttpClientInterface;
class ShopfullyService
{
    private const API_KEY = 'e1515941-38d4-4ecf-b5fb-3970adbefb1d';
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function fetchBrochureData(string $brochureId, string $locale): array
    {
        $url = sprintf(
            'https://d1h08qwp2t1dnu.cloudfront.net/v1/%s/flyers.json?conditions[id]=%s',
            $locale,
            $brochureId
        );

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'x-api-key' => self::API_KEY,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to fetch brochure data');
        }

        return $response->toArray();
    }

    public function fetchPublicationData(int $brochureId, string $locale = 'it_it'): array
    {
        $url = sprintf(
            'https://d1h08qwp2t1dnu.cloudfront.net/v1/'.$locale.'/flyers/%u/publications.json?modifiers=pdf_url',
            (int) $brochureId
        );


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