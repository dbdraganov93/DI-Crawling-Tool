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

    public function getBrochure(string $brochureId, string $locale): array
    {
        $response['brochureData'] = $this->fetchBrochureData($brochureId, $locale);
        $response['publicationData'] = $this->fetchPublicationData($response['brochureData']['publication_id'], $locale);

        return $response;
    }

    public function fetchBrochureData(string $brochureId, string $locale): array
    {
        $url = 'https://d1h08qwp2t1dnu.cloudfront.net/v1/'. $locale .'/flyers/'. $brochureId .'.json';

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
        $url = 'https://d1h08qwp2t1dnu.cloudfront.net/v1/'.$locale.'/publications/'.$brochureId.'.json?modifiers=pdf_url';


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