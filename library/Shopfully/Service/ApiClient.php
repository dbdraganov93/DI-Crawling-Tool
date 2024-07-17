<?php

require_once APPLICATION_PATH . '/../vendor/autoload.php';

class Shopfully_Service_ApiClient
{
    private $host;
    private $apiKey;
    private $baseUrl = 'https://%s/v1/%s/';

    public function __construct(string $lang)
    {
        $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', 'production');
        $this->host = $configIni->shopfully->apiHost;
        $this->apiKey = $configIni->shopfully->apiKey;

        $this->baseUrl = sprintf($this->baseUrl, $this->host, $lang);
    }

    public function fetchData(string $endpoint, array $params = [], string $method = Zend_Http_Client::GET): ?array
    {
        $client = new Zend_Http_Client($this->baseUrl . $endpoint);
        $client->setHeaders([
            'x-api-key' => $this->apiKey,
            'Postman-Token' => '<calculated when request is sent>',
            'Host' => $this->host,
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
        ]);

        if (!empty($params)) {
            $client->setParameterGet($params);
        }

        $response = $client->request($method);

        if (!$response->isSuccessful()) {
            throw new Exception('API request failed with status code: ' . $response->getStatus());
        }

        $responseData = json_decode($response->getBody(), true);
        return $responseData['data'] ?? null;
    }

    public function getBrochuresThatStartAt(string $data, int $page = 1): ?array
    {
        try {
            $brochures =  $this->fetchData(sprintf('flyers.json?conditions[is_active]=1&conditions[start_date]=%s&page=%s&modifiers=deduplication', $data, $page));
        } catch (Exception $e) {
            $brochures = null;
        }

        return $brochures;
    }

    public function getPublicationImages(int $publicationId, int $page = 1, string $assetsLevel = 'level_5'): ?array
    {
        try {
            $images = $this->fetchData(sprintf('publications/%u/publication_page_assets.json?conditions[name]=%s&page=%s', $publicationId, $assetsLevel, $page));
        } catch (Exception $e) {
            $images = null;
        }
        return $images;
    }

    public function getClientBrochures(int $shopfullyClientId, int $page = 1): ?array
    {
        try {
            $brochures =  $this->fetchData(sprintf('retailers/%s/flyers.json?page=%s&conditions[is_active]=1&modifiers=deduplication', $shopfullyClientId, $page));
        } catch (Exception $e) {
            $brochures = null;
        }

        return $brochures;
    }

    public function getBrochure(int $brochureId): ?array
    {
        return $this->fetchData(sprintf('flyers/%u.json', $brochureId));
    }

    public function getBrochurePdf(int $brochureId): ?array
    {
        $parameters = [
            'modifiers' => 'pdf_url',
        ];

        return $this->fetchData(sprintf('flyers/%u/publications.json?modifiers=pdf_url', $brochureId), $parameters);
    }

    public function getBrochureClickout(int $brochureId): ?array
    {
        return $this->fetchData(sprintf('flyers/%u/flyer_gibs.json', $brochureId));
    }

    public function getAllStoresByCompanyId(int $companyId, $page = 1): ?array
    {
        try {
            $parameters = [
                'conditions[retailer_id]' => $companyId,
            ];

            $brochures =  $this->fetchData(sprintf('stores.json?page=%s', $page), $parameters);
        } catch (Exception $e) {
            $brochures = null;
        }

        return $brochures;
    }

    public function getStoresByBrochureId(int $brochureId, $page = 1): ?array
    {
        try {
            $stores = $this->fetchData(sprintf('flyers/%u/stores.json?page=%s', $brochureId, $page));
        } catch (Exception $e) {
            $stores = null;
        }

        return $stores;
    }

    public function getStoresById(int $storeId): ?array
    {
        try {
            $store = $this->fetchData(sprintf('stores.json?conditions[id]=%s', $storeId));
        } catch (Exception $e) {
            $store = null;
        }

        return $store;
    }

    public function getRetailerById(int $retailerId): ?array
    {
        try {
            $retailer = $this->fetchData(sprintf('retailers.json?conditions[id]=%s', $retailerId));
        } catch (Exception $e) {
            $retailer = null;
        }

        return $retailer;
    }

    public function getRetailerByName(string $retailerName): ?array
    {
        $retailerName = str_replace(' ', '+', $retailerName);

        try {
            $retailer = $this->fetchData(sprintf('retailers.json?conditions[name]=%s', $retailerName));
        } catch (Exception $e) {
            $retailer = null;
        }

        return $retailer;
    }

    public function getCategoryById(int $categoryId): ?array
    {
        try {
            $category = $this->fetchData(sprintf('categories.json?conditions[id]=%s', $categoryId));
        } catch (Exception $e) {
            $category = null;
        }

        return $category;
    }
}
