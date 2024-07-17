<?php

namespace Marktjagd\Service\IprotoApi;

use Crawler_Generic_Response;
use Marktjagd_Collection_Api_Article;
use Marktjagd_Collection_Api_Distribution;
use Marktjagd_Collection_Api_Store;
use Marktjagd_Database_Entity_CrawlerConfig;
use Marktjagd_Entity_Api_Article;
use Marktjagd_Entity_Api_Distribution;
use Marktjagd_Entity_Api_Store;
use Marktjagd_Service_Output_File;
use Zend_Config_Ini;
use Zend_Log;
use Zend_Registry;

// XXX: The oauth-client used by the blender-api does not support autoloading:
require_once APPLICATION_PATH . '/../library/Marktjagd/Service/IprotoApi/IprotoApiClient.php';

function array_map_recursive(&$arr, $fn)
{
    return array_map(function ($item) use ($fn) {
        return is_array($item) ? array_map_recursive($item, $fn) : $fn($item);
    }, $arr);
}

/**
 * All input and output API-functions for the iProto-API.
 */
class ApiServiceIproto implements ApiServiceInterface
{
    protected \Zend_Log $logger;
    protected \IprotoApiClient $oauthClient;
    protected Zend_Config_Ini $config;
    protected string $imageServer;

    public function __construct(string $env = APPLICATION_ENV)
    {
        $this->logger = Zend_Registry::get('logger');
        $this->oauthClient = new \IprotoApiClient($env);
        $this->config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/apiClient.ini', $env);

        // TODO: At the time of writing the domain is not returned by the iProto-API. Once it is, this can be refactored:
        if ($env == 'testing') {
            $this->imageServer = 'https://image-service-cloudfront.ws-test.offerista.com';
        } else if ($env == 'staging') {
            $this->imageServer = 'https://image-service-cloudfront.ws-stage.offerista.com';
        } else {
            $this->imageServer = 'https://media.marktjagd.com';
        }
    }

    /**
     * Sends an api-request to the iProto-api and returns the result as an associative array.
     *
     * If you have body and send json request you need to pass already json_encode body.
     */
    public function sendRequest(string $method, string $uri, array $params = [], $body = null, $bodyMediaType = 'application/ld+json'): array
    {
        if (count($params) > 0) {
            // The function http_build_query converts booleans like true into the integer 1; however, because of "reasons"
            // api-platform in iProto crashes if we pass in 1 as boolean instead of the string "true"…
            $params = array_map_recursive($params, function ($value) {
                if ($value === true) return 'true';
                elseif ($value === false) return 'false';
                else return $value;
            });
            $params = '?' . http_build_query($params);
        } else $params = '';
        $url = $this->config->config->iproto->host . '/' . ltrim($uri, '/') . $params;
        $curlOpt = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => ['Authorization:Bearer ' . $this->oauthClient->getIprotoApiToken()],
        ];

        if ($method == 'PUT') $curlOpt[CURLOPT_PUT] = true;
        else if ($method == 'POST') $curlOpt[CURLOPT_POST] = true;
        else if ($method == 'DELETE') $curlOpt[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        else if ($method == 'GET') ;
        else throw new \LogicException("unexpected http-method $method");
        if ($body) {
            $curlOpt[CURLOPT_HTTPHEADER][] = 'Content-type: ' . $bodyMediaType;
            $curlOpt[CURLOPT_POSTFIELDS] = $body;
        }

        $ch = curl_init();

        $x = 1;
        $maxApiAttempts = 6;
        $code = null;
        while ($x < $maxApiAttempts) {
            $this->logger->info("sending iproto api-request (attempt $x): $method $url");
            sleep(pow(($x - 1), 2));
            curl_setopt_array($ch, $curlOpt);
            $body = curl_exec($ch);
            if ($body === false) return ["iproto-api request failed ($method $url): " . curl_error($ch)];
            $code = curl_getinfo($ch)['http_code'];
            if ($code >= 200 && $code < 503) {
                break;
            }
            $x++;
            $this->logger->warn("HTTP response code $code: " . $body);
        }

        curl_close($ch);
        if ($code >= 500 || ($method == 'GET' && $code != 200)) throw new \RuntimeException("iproto-api request failed with code $code ($method $url)");

        $response = [
            'code' => $code,
        ];
        if ($code >= 200 && $code <= 299) {
            $response['body'] = json_decode($body, true);
        }
        return $response;
    }

    public function createStore(array $storeData): void
    {
        $request = $this->sendRequest('POST', '/api/stores',
            [],
            json_encode($storeData),
            'application/json',
        )['body'];
    }

    public function findCompanyByName(int $ownerId, string $companyName): ?array
    {
        $companyData = [];
        $response = $this->sendRequest('GET', '/api/integrations', [
            'owner' => $ownerId,
            'searchIntegrationByTitleAndId' => $companyName,
            'exists' => ['deletedAt' => false],
            'order' => ['title' => 'asc'],
        ]);

        $companies = $response['body']['hydra:member'];

        if (count($companies) == 0) {
            return NULL;
        }

        if (count($companies) > 1) {
            foreach ($companies as $company) {
                if (strtolower($companyName) === strtolower($company['title'])) {
                    $companyData = $this->mapCompanyToApi3($company);
                    break;
                }
            }
        }
        $companyData = $companyData?: reset($companies);

        return $this->mapCompanyToApi3($companyData);
    }

    public function findStoresByCompany(int $companyId, bool $visibleOnly = true): Marktjagd_Collection_Api_Store
    {
        // The original implementation of this function returns all distribution-names (named sales-regions) to which
        // stores are mapped, which we build via these nested api-requests:
        $storeToDistributionMapping = [];
        foreach ($this->findDistributionsByCompany($companyId)->getElements() as $distribution) {
            /* @var Marktjagd_Entity_Api_Distribution $distribution */
            foreach ($this->findStoresByDistribution($companyId, $distribution->getTitle())->getElements() as $store) {
                /* @var Marktjagd_Entity_Api_Store $store */
                $storeToDistributionMapping[$store->getId()][] = $distribution->getTitle();
            }
        }

        // Fetch all stores of the given company, regardless of any sales-region mapping, adding the sales-region info from above:
        $collection = new Marktjagd_Collection_Api_Store();
        $page = 1;
        $pageSize = 100;
        do {
            $params = [
                'integration' => '/api/integrations/' . $companyId,
                'page' => $page++,
                'itemsPerPage' => $pageSize,
            ];
            if ($visibleOnly) $params['exists'] = ['deletedAt' => false]; // Exclude the deleted ones
            $response = $this->sendRequest('GET', '/api/stores', $params);
            $stores = $response['body']['hydra:member'];
            foreach ($stores as $store) {
                $element = $this->mapStoreToStoreEntity($store);
                if (array_key_exists($element->getId(), $storeToDistributionMapping)) {
                    // The store is part of one or multiple named sales-regions:
                    $element->setDistribution(implode(',', $storeToDistributionMapping[$element->getId()]));
                }
                $collection->addElement($element);
            }
        } while (count($stores) == $pageSize);


        return $collection;
    }

    function findDistributionsByCompany(int $companyId, ?string $title = null): Marktjagd_Collection_Api_Distribution
    {
        $collection = new Marktjagd_Collection_Api_Distribution();
        $page = 1;
        $pageSize = 100;
        do {
            $params = [
                'integration' => '/api/integrations/' . $companyId,
                'type' => 'named',
                'page' => $page++,
                'itemsPerPage' => $pageSize,
            ];
            if ($title !== null) $params['exact_title'] = $title;
            $response = $this->sendRequest('GET', '/api/sales_regions', $params);
            $salesRegions = $response['body']['hydra:member'];
            foreach ($salesRegions as $salesRegion) {
                $element = new Marktjagd_Entity_Api_Distribution();
                $element
                    ->setCompanyId($companyId)
                    ->setDistributionId($salesRegion['id'])
                    ->setTitle($salesRegion['title'])
                    ->setStoreCount(null) // XXX: This is not supported by iProto at this moment.
                ;
                $collection->addElement($element);
            }

        } while (count($salesRegions) == $pageSize);

        return $collection;
    }

    public function findStoresByDistribution(int $companyId, ?string $distribution, bool $excludeDistribution = false, bool $visibleOnly = true)
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        if ($distribution !== null) {
            if (!$visibleOnly) {
                throw new \LogicException('iproto does no longer map removed stores to sales-regions; when a store is undeleted, it is dynamically re-added to sales-regions targeting it');
            }
            $distributions = $this->findDistributionsByCompany($companyId, $distribution);
            if (count($distributions->getElements()) == 0) {
                $logger->log("Couldn't find any distribution for company $companyId and title $distribution", Zend_Log::ERR);
                return false;
            } else if (count($distributions->getElements()) > 1) {
                $logger->log("Found " . count($distributions->getElements()) . " distributions for company $companyId and title $distribution, which is unexpected", Zend_Log::ERR);
                return false;
            }
            $salesRegionId = (int)array_key_first($distributions->getElements());
        } else {
            if ($excludeDistribution) throw new \LogicException('unable to inverse selection without any given distribution-title');
            $salesRegionId = null;
        }

        // Find all stores which are mapped to the given sales-region:
        $storesInDistribution = new Marktjagd_Collection_Api_Store();
        $page = 1;
        $pageSize = 100;
        if ($salesRegionId !== null) {
            do {
                $response = $this->sendRequest('GET', '/api/sales_region_store_mappings', [
                    'salesRegion' => '/api/sales_regions/' . $salesRegionId,
                    'page' => $page++,
                    'itemsPerPage' => $pageSize,
                ]);
                $mappedStores = $response['body']['hydra:member'];
                foreach ($mappedStores as $mappedStore) {
                    $store = $this->mapStoreToStoreEntity($mappedStore['store']);
                    $store->setDistribution($distribution);
                    $storesInDistribution->addElement($store);
                }
            } while (count($mappedStores) == $pageSize);
        }

        // If we either were not looking for any distribution or want to negate the selection, fetch all stores:
        if ($salesRegionId === null || $excludeDistribution) {
            if ($excludeDistribution) {
                $storeIdsToExclude = array_keys($storesInDistribution->getElements());
            } else {
                $storeIdsToExclude = [];
            }
            $allStores = $this->findStoresByCompany($companyId, $visibleOnly);
            $collection = new Marktjagd_Collection_Api_Store();
            foreach ($allStores->getElements() as $storeId => $storeEntity) {
                if (!in_array($storeId, $storeIdsToExclude)) {
                    $collection->addElement($storeEntity);
                }
            }
            return $collection;
        } else {
            return $storesInDistribution;
        }
    }

    public function findBrochureByBrochureNumberAndCompany(string $brochureNumber, int $companyId): ?array
    {
        $response = $this->sendRequest('GET', '/api/brochures', [
                'integration' => $companyId,
                'brochureNumber' => $brochureNumber,
            ])['body'];

        return $response['hydra:member'] ?: null;
    }

    public function findActiveBrochuresByCompany(int $companyId)
    {
        $list = [];
        $page = 1;
        $pageSize = 100;
        do {
            $response = $this->sendRequest('GET', '/api/brochures', [
                'integration' => '/api/integrations/' . $companyId,
                'exists' => [
                    'deletedAt' => false,
                ],
                'timeConstraint' => [
                    'current' => true,
                    'upcoming' => true,
                    'future' => true,
                ],
                'page' => $page++,
                'itemsPerPage' => $pageSize,
                'with_layout' => true,
            ]);
            $brochures = $response['body']['hydra:member'];

            foreach ($brochures as $brochure) {
                $list[$brochure['id']] = [
                    'brochureNumber' => $brochure['brochureNumber'],
                    'title' => $brochure['title'],
                    'validFrom' => $brochure['validFrom'],
                    'validTo' => $brochure['validTo'],
                    'visibleFrom' => $brochure['visibleFrom'],
                    'visibleTo' => $brochure['validTo'],
                    'lastModified' => $brochure['updatedAt'],
                    'created' => $brochure['createdAt'],
                    'layout' => $brochure['layout'],

                    // Type/Type-ID does not exist anymore; it's now replace by the sales-region-id:
                    'type' => null,
                    'type_id' => null,
                ];
            }
        } while (count($brochures) == $pageSize);

        return $list;
    }

    public function findStoresWithActiveBrochures(int $brochureId, int $companyId)
    {
        // XXX: Ideally we drop this function, since it currently cannot be answered easily via the iProto-API.
        throw new \BadMethodCallException('not implemented for iProto-API');
    }

    public function findActiveArticlesByCompany(int $companyId)
    {
        $list = [];
        $page = 1;
        $pageSize = 1000;
        do {
            $response = $this->sendRequest('GET', '/api/products', [
                'integration' => '/api/integrations/' . $companyId,
                'exists' => [
                    'deletedAt' => false,
                ],
                'page' => $page++,
                'itemsPerPage' => $pageSize,
            ]);
            $products = $response['body']['hydra:member'];
            foreach ($products as $product) {
                // For unknown reasons  the original implementation of this function only returned selected, renamed
                // attributes of the articles:
                $list[$product['id']] = [
                    'title' => $product['title'],
                    'articleNumber' => $product['productNumber'],
                    'validFrom' => $product['validFrom'],
                    'validTo' => $product['validTo'],
                    'visibleFrom' => $product['visibleFrom'],
                    'visibleTo' => $product['validTo'],
                    'created' => $product['createdAt'],
                ];
            }
        } while (count($products) == $pageSize);

        return $list;
    }

    public function findAllStoresForCompany(int $companyId, string $status = 'visible')
    {
        $list = [];
        $page = 1;
        $pageSize = 100;
        do {
            $response = $this->sendRequest('GET', '/api/stores', [
                'integration' => '/api/integrations/' . $companyId,
                'exists' => ['deletedAt' => $status == 'visible' ? false : true],
                'page' => $page++,
                'itemsPerPage' => $pageSize,
            ]);
            $stores = $response['body']['hydra:member'];
            foreach ($stores as $store) {
                $list[$store['id']] = $this->mapStoreToApi3($store);
            }
        } while (count($stores) == $pageSize);

        return $list;
    }

    public function findLastImport(int $companyId, string $type, string $status = 'done')
    {
        if ($type == 'articles') $type = 'products';
        $response = $this->sendRequest('GET', '/api/imports', [
            'integration' => '/api/integrations/' . $companyId,
            'type' => $type, // This filter is partial and will find "stores" as well as "stores:api3".
            'status' => $status,
            'itemsPerPage' => 1,
            'order' => ['updatedAt' => 'desc'],
        ])['body'];
        if ($response['hydra:totalItems'] === 0) return false;
        return $response['hydra:member']['0']['updatedAt'];
    }

    public function findStoreByStoreId(int $storeId, int $companyId)
    {
        $response = $this->sendRequest('GET', '/api/stores/' . $storeId)['body'];
        if ($response['deletedAt']) return false;
        return $this->mapStoreToApi3($response);
    }

    public function findStoreByStoreNumber(string $storeNumber, string $companyId = '')
    {
        $list = [];
        $parameters = [
            'storeNumber' => $storeNumber,
            'deletedAt' => false,
        ];

        if ($companyId) {
            $parameters['integration'] = $companyId;
        }

        $response = $this->sendRequest('GET', '/api/stores', $parameters)['body'];

        $stores = $response['hydra:member'];
        foreach ($stores as $store) {
            $list[$store['id']] = $this->mapStoreToApi3($store);
        }

        return $list;
    }

    public function findStoreNumbersByPostcode(string $postcode, int $companyId)
    {
        $storeNumbers = [];

        try {
            $response = $this->sendRequest('GET', '/api/stores?integration='.$companyId.'&page=1&exists[deletedAt]=false&searchStoreByTitleAndAddress=' . $postcode . '&itemsPerPage=10&order[id]=desc')['body'];
            if (!empty($response['hydra:member'])) {
                foreach ($response['hydra:member'] as $store) {
                    if ($postcode == $store['postalCode']) {
                        $storeNumbers[] = $store['storeNumber'];
                    }
                }
            }
        } catch (Exception $ex) {}

        return $storeNumbers;
    }

    public function findStoresWithBrochures(int $companyId, string $timeConstraint = 'current')
    {
        // XXX: Ideally we drop this function, since it currently cannot be answered easily via the iProto-API.
        throw new \BadMethodCallException('not implemented for iProto-API');
    }

    public function findCompanyByCompanyId(int $companyId, $industryId = false)
    {
        $response = $this->sendRequest('GET', '/api/integrations/' . $companyId)['body'];
        if ($response['deletedAt'] || !$response['isVisible']) return false;
        if ($industryId !== false && $response['primaryIndustry'] != '/api/industries/' . $industryId) return false; // Not sure what the purpose of this industry-argument is, but here we are…
        return $this->mapCompanyToApi3($response);
    }

    public function findManufacturerTagByArticleId(int $companyId, int $articleId)
    {
        throw new \LogicException('manufacturer tags are no longer supported in iProto (there are only generic keywords)');
    }

    public function findArticleById(int $companyId, string $id)
    {
        $response = $this->sendRequest('GET', '/api/products/'.$id)['body'];
        return $response;
    }

    public function findArticleByArticleNumber(int $companyId, string $articleNumber)
    {
        $response = $this->sendRequest('GET', '/api/products', [
            'integration' => '/api/integrations/' . $companyId,
            'productNumber' => $articleNumber,
            'exists' => [
                'deletedAt' => false,
            ],
            'timeConstraint' => [
                'current' => true,
                'upcoming' => true,
                'future' => true,
            ],
            'itemsPerPage' => 1,
        ])['body'];
        if ($response['hydra:totalItems'] === 0) return false;
        return $this->mapProductToApi3($response['hydra:member'][0]);
    }

    public function findUpcomingArticleByNumber(int $companyId, string $articleNumber)
    {
        $response = $this->sendRequest('GET', '/api/products', [
            'integration' => '/api/integrations/' . $companyId,
            'productNumber' => $articleNumber,
            'exists' => [
                'deletedAt' => false,
            ],
            'timeConstraint' => [
                'future' => true,
            ],
            'itemsPerPage' => 1,
        ])['body'];
        if ($response['hydra:totalItems'] === 0) {
            return false;
        }
        return $this->mapProductToApi3(reset($response['hydra:member']));
    }

    public function getActiveArticleCollection(int $companyId)
    {
        $collection = new Marktjagd_Collection_Api_Article();
        $page = 1;
        $pageSize = 1000;

        do {
            $response = $this->sendRequest('GET', '/api/products', [
                'integration' => '/api/integrations/' . $companyId,
                'exists' => [
                    'deletedAt' => false,
                ],
                'timeConstraint' => [
                    'current' => true,
                    'upcoming' => true,
                    'future' => true,
                ],
                'page' => $page++,
                'itemsPerPage' => $pageSize,
            ]);
            $products = $response['body']['hydra:member'];
            foreach ($products as $product) {
                $element = new Marktjagd_Entity_Api_Article();
                $element
                    ->setArticleId($product['id'])
                    ->setArticleNumber($product['productNumber'])
                    ->setTitle($product['title'])
                    ->setText($product['description'])
                    ->setEan($product['gtin'])
                    ->setPrice($product['price'])
                    ->setShipping($product['shipping'])
                    ->setSuggestedRetailPrice($product['manufacturerPrice'])
                    ->setArticleNumberManufacturer($product['manufacturerNumber'])
                    ->setUrl($product['url'])
                    ->setSize($product['size'])
                    ->setColor($product['color'])
                    ->setAmount($product['amount'])
                    ->setStart($product['validFrom'])
                    ->setEnd($product['validTo'])
                    ->setVisibleStart($product['visibleFrom'])
                    ->setVisibleEnd($product['validTo'])
                    ->setImage($this->getImageUrl(@$product['images'][0]));

                $collection->addElement($element, true, 'complex', false);
            }
        } while (count($products) == $pageSize);

        return $collection;
    }

    public function import(Marktjagd_Database_Entity_CrawlerConfig $eCrawlerConfig, Crawler_Generic_Response $response): Crawler_Generic_Response
    {
        if (!$response->getIsImport()) return $response;
        try {
            $companyId = $eCrawlerConfig->getCompany()->getIdCompany();

            // Determine the type of import we want to start and depending on the type and size of the file, also
            // adjust the limits for the import-job:
            $type = $eCrawlerConfig->getCrawlerType()->getType();
            $memory = null;
            $executionTimeout = null;
            if ($type == 'pdfs' || $type == 'brochures') {
                $type = 'brochures:api3';
                // XXX: This can be removed once the pdf parallel-processing has been activated:
                if ($response->getCountElements() >= 1000) {
                    $executionTimeout = 12 * 3600; // 12 hours
                } elseif ($response->getCountElements() >= 100) {
                    $executionTimeout = 6 * 3600; // 6 hours
                } elseif ($response->getCountElements() >= 50) {
                    $executionTimeout = 3 * 3600; // 3 hours
                } else {
                    $executionTimeout = 2 * 3600; // 2 hours
                    $memory = 1024;
                }
            } else if ($type == 'articles') {
                $type = 'products:api3';
                if ($response->getCountElements() >= 1000) {
                    // Assume up to 3 seconds for each product as worst case with the bounds being 1h to max 24h:
                    $executionTimeout = min(86400, max(3600, $response->getCountElements() * 3));
                    // Scale memory-consumption at 1 GiB / 10k products with min 1GiB and max 8GiB (rounded up to 512MiB blocks):
                    $memory = min(8096, max(1024, ceil($response->getCountElements() / 5000.0) * 512));
                } else {
                    $executionTimeout = 3600;
                    $memory = 1024;
                }
            } else if ($type == 'stores') {
                $type = 'stores:api3';
            } else {
                throw new \InvalidArgumentException("unexpected import-type '$type'");
            }

            // Determine the import-options:
            $behavior = $eCrawlerConfig->getCrawlerBehaviour()->getBehaviour();
            if ($behavior == 'remove') {
                // Delete content which is not in the import-list:
                $options = [
                    'appendOnly' => false,
                    'archive' => false,
                    'ignoreIfNotStaged' => true,
                ];
            } else if ($behavior == 'archive') {
                // Expire content which is not in the import-list (offers only):
                $options = [
                    'appendOnly' => false,
                    'archive' => true,
                    'ignoreIfNotStaged' => true,
                ];
            } else if ($behavior == 'keep') {
                // Don't touch content which is not in the import-list (append only):
                $options = [
                    'appendOnly' => true,
                    'ignoreIfNotStaged' => true,
                ];
            } else if ($behavior == 'auto') {
                // Don't delete content which does not have the same time-constraint as in the import-list:
                $options = [
                    'appendOnly' => false,
                    'archive' => false,
                    'ignoreIfNotStaged' => true,
                    'ignoreOtherTimeConstraints' => true,
                ];
            } else {
                // Default or unspecified case => delete:
                $options = [
                    'appendOnly' => false,
                    'archive' => false,
                    'ignoreIfNotStaged' => true,
                ];
            }

            // Determine where to find the import-file:
            $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            if ($configCrawler->crawler->s3->active) {
                // For S3, the URL to the file is already in the response.
                $url = $response->getFileName();
                if (preg_match('#^https://s3.eu-west-1.amazonaws.com/(.*)$#', $url, $matches)) {
                    // Use the S3-protocol directly instead of going through http first (which would involve leaving the private cloud from a network perspective):
                    $url = 's3://' . $matches[1];
                }
            } else {
                $url = Marktjagd_Service_Output_File::generatePublicUrl($response->getFileName());
            }
            if (!$url) throw new \RuntimeException("unable to determine url for import-file '" . $response->getFileName() . "' (" . ucwords($type) . "-crawler for company $companyId)");

            // Create Import-Job:
            $payload = [
                'integration' => '/api/integrations/' . $companyId,
                'type' => $type,
                'integrationOptions' => $options,
                'url' => $url,
                'memory' => $memory,
                'executionTimeout' => $executionTimeout,
            ];
            $apiResponse = $this->sendRequest('POST', '/api/imports', [], json_encode($payload))['body'];
            $response->setImportId($this->convertIprotoIdToInt($apiResponse['@id']));
            $response->setLoggingCode(Crawler_Generic_Response::IMPORT_PROCESSING);

        } catch (\Throwable $t) {
            $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE_ADD);
            throw $t;
        }

        return $response;
    }

    public function createSalesRegionForTheWholeCountry(int $integrationId, string $countryCode) {
        $iprotoCreateSalesRegionRequest = [
            'integration' => '/api/integrations/' . $integrationId,
            'countries' => [
                '/api/countries/' . $countryCode
            ],
            'type' => 'on_demand',
        ];

        return $this->createSalesRegion($iprotoCreateSalesRegionRequest);
    }

    public function createSalesRegionFromStoreNumbers(int $integrationId, array $storeNumbers): array
    {
        //convert distribution request to iproto sales region create request
        $storeSelectors = [];
        foreach ($storeNumbers as $storeNumber) {
            $storeSelectors['store_id_'.$storeNumber] = [
                'targetType' => 'store_number',
                'targetIntegration' => '/api/integrations/' . $integrationId,
                'targetSelector' => (string) $storeNumber,
            ];
        }

        $iprotoCreateSalesRegionRequest = [
            'integration' => '/api/integrations/' . $integrationId,
            'storeSelectors' => $storeSelectors,
            'type' => 'on_demand',
        ];

        return $this->createSalesRegion($iprotoCreateSalesRegionRequest);
    }

    public function createSalesRegion(array $iprotoCreateSalesRegionRequest)
    {
        try {
            $response = $this->sendRequest('POST', '/api/sales_regions',
                [],
                json_encode($iprotoCreateSalesRegionRequest),
            )['body'];
        } catch (\Throwable $exception) {
            print_r($exception->getMessage());
            echo "\n\n";
            print_r($exception->getResponse()->getBody()->getContents());
            echo "\n\n";
            throw $exception;
        }

        return $response;
    }

    public function createBrochure(array $brochure): array
    {
        try {
            $response = $this->sendRequest('POST', '/api/brochures',
                [],
                json_encode($brochure),
                'application/json'
            );

            $decodedResponse = $response['body'];
        } catch (\Throwable $exception) {
            print_r($exception->getMessage());
            echo "\n\n";
            print_r($exception->getResponse()->getBody()->getContents());
            echo "\n\n";
            throw $exception;
        }


        return $decodedResponse;
    }

    /**
     * Some IDs in iProto are written as "/api/<resource>/<id>" or even as inlined sub-resources, which this function converts to <id>.
     * @param string|array $iProtoId
     * @return int
     */
    protected function convertIprotoIdToInt($iProtoId): int
    {
        if (is_array($iProtoId)) {
            if (!array_key_exists('@id', $iProtoId)) throw new \RuntimeException('id-attribute not found in inline resource');
            return (int)$iProtoId['@id'];
        }
        return (int)preg_replace('/^.*?([0-9]+)/', '$1', $iProtoId);
    }

    /**
     * Translates iProto integration-attributes to API3 company-attributes.
     * XXX: Only matching attributes are mapped, attributes missing in iProto are set to null and attributes not in APIv3 are omitted.
     */
    protected function mapCompanyToApi3(array $data): array
    {
        return [
            'id' => $data['id'],
            'status' => $data['deletedAt'] ? 'removed' : ($data['isVisible'] ? 'visible' : 'hidden'),
            'title' => $data['title'],
            'industry_id' => $this->convertIprotoIdToInt($data['primaryIndustry']),
            'description' => $data['description'],
            'street' => $data['street'],
            'street_number' => $data['streetNumber'],
            'zipcode' => $data['postalCode'],
            'city' => $data['city'],
            'facebook_url' => $data['facebookUrl'],
            'homepage' => $data['companyUrl'],
            'email' => $data['email'],
            'phone_number' => $data['phone'],
            'fax_number' => $data['fax'],
            'default_radius' => $data['visibilityRadius'],
            'datetime_created' => $data['createdAt'],
            'datetime_modified' => $data['updatedAt'],
            'datetime_removed' => $data['deletedAt'],

            // Attributes which don't exist anymore:
            'partner' => null,
            'number' => null,
            'product_id' => null,
            'product_temp_id' => null,
            'slogan' => null,
            'legal_form' => null,
            'price_level' => null,
            'category' => null,
            'google_plus_url' => null,
            'imprint' => null,
            'has_articles' => null,
            'has_brochures' => null,
            'has_coupons' => null,
            'external_tracking_id' => null,
        ];
    }

    protected function getImageUrl(?array $imageData): ?string
    {
        if ($imageData === null) return null;
        return $this->imageServer . '/' . preg_replace('#.+\/(\d+)$#', '$1', $imageData['image']) . '_' . $imageData['width'] . 'x' . $imageData['height'] . '.' . $imageData['type'];
    }

    /**
     * Translates iProto product-attributes to API3 article-attributes.
     * XXX: Only matching attributes are mapped, attributes missing in iProto are set to null and attributes not in APIv3 are omitted.
     */
    protected function mapProductToApi3(array $data): array
    {
        return [
            'id' => $data['id'],
            'number' => $data['productNumber'],
            'status' => $data['deletedAt'] ? 'removed' : 'visible',
            'company_id' => $this->convertIprotoIdToInt($data['integration']),
            'title' => $data['title'],
            'description' => $data['description'],
            'ean' => $data['gtin'],
            'price' => $data['price'],
            'price_multiple' => $data['priceIsVariable'],
            'shipping' => $data['shipping'],
            'manufacturer_price' => $data['manufacturerPrice'],
            'manufacturer_number' => $data['manufacturerNumber'],
            'url' => $data['url'],
            'size' => $data['size'],
            'color' => $data['color'],
            'amount' => $data['amount'],
            'datetime_from' => $data['validFrom'],
            'datetime_to' => $data['validTo'],
            'datetime_visible_from' => $data['visibleFrom'],
            'datetime_visible_to' => $data['validTo'],
            'datetime_created' => $data['createdAt'],
            'datetime_modified' => $data['updatedAt'],
            'datetime_removed' => $data['deletedAt'],
            'tracking_bugs' => $data['trackingPixels'],
            // XXX: The old function in the APIv3 client just extracts the first image, which is mad, but we should stay compatible:
            'image' => $this->getImageUrl(@$data['images'][0]),

            // Attributes which don't exist anymore:
            'partner' => null,
            'number_is_generated' => null,
            'catalog_id' => null,
            'category_id' => null,
            'time_constraint' => null,
            'distance' => null,
            'score' => null,
            'external_tracking_id' => null,
        ];
    }

    protected function convertOpeningHoursToString(array $openingHours): string
    {
        $elements = [];
        foreach ($openingHours as $day => $hours) {
            if ($hours === null || count($hours) == 0) continue;
            foreach ($hours as $hourSet) {
                $elements[] = "$day {$hourSet['start']}-{$hourSet['end']}";
            }
        }
        return implode(',', $elements);
    }

    /**
     * Translates iProto store-attributes to API3 store-attributes.
     * XXX: Only matching attributes are mapped, attributes missing in iProto are set to null and attributes not in APIv3 are omitted.
     */
    protected function mapStoreToApi3(array $data): array
    {
        if ($data['openingHours'] !== null && count($data['openingHours']) > 0) {
            $storeHours = $this->convertOpeningHoursToString($data['openingHours']);
        } else {
            $storeHours = null;
        }

        return [
            'id' => $data['id'],
            'number' => $data['storeNumber'],
            'status' => $data['deletedAt'] ? 'removed' : 'visible',
            'company_id' => $this->convertIprotoIdToInt($data['integration']),
            'title' => $data['title'],
            'subtitle' => $data['subtitle'],
            'street' => $data['street'],
            'street_number' => $data['streetNumber'],
            'zipcode' => $data['postalCode'],
            'city' => $data['city'],
            'longitude' => $data['longitude'],
            'latitude' => $data['latitude'],
            'phone_number' => $data['phone'],
            'fax_number' => $data['fax'],
            'email' => $data['email'],
            'payment' => $data['paymentOptions'],
            'parking' => $data['parkingOptions'],
            'barrier_free' => $data['barrierFree'],
            'bonus_card' => $data['bonusCards'],
            'section' => $data['sections'],
            'service' => $data['services'],
            'toilet' => $data['customerToilet'],
            'datetime_created' => $data['createdAt'],
            'datetime_modified' => $data['updatedAt'],
            'datetime_removed' => $data['deletedAt'],
            'has_images' => null, // Store images are no longer supported in iProto
            'store_hours' => $storeHours,

            // These attributes had different index-names in different calls to the api-wrapper…
            'text' => $data['description'],
            'description' => $data['description'],
            'website' => $data['url'],
            'homepage' => $data['url'],
            'store_hours_notes' => $data['openingHoursNotes'],
            'hours_text' => $data['openingHoursNotes'],

            // Attributes which don't exist anymore:
            'partner' => null,
            'number_is_generated' => null,
            'address_id' => null,
            'city_id' => null,
            'category' => null,
            'has_articles' => null,
            'has_brochures' => null,
            'has_coupons' => null,
            'distance' => null,
            'score' => null,
            'num_others' => null,
            'has_partner_priority' => null,
            'tracking_bugs' => null,
            'external_tracking_id' => null,
        ];
    }

    /**
     * Translates iProto store-attributes to the old crawler-framework's representation.
     */
    protected function mapStoreToStoreEntity(array $data): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();
        $store
            ->setId($data['id'])
            ->setStoreNumber($data['storeNumber'])
            ->setTitle($data['title'])
            ->setSubtitle($data['subtitle'])
            ->setText($data['description'])
            ->setStreet($data['street'])
            ->setStreetNumber($data['streetNumber'])
            ->setZipcode($data['postalCode'])
            ->setCity($data['city'])
            ->setLatitude($data['latitude'])
            ->setLongitude($data['longitude'])
            ->setPayment($data['paymentOptions'])
            ->setWebsite($data['url'])
            ->setEmail($data['email'])
            ->setPhone($data['phone'])
            ->setFax($data['fax'])
            ->setStoreHours($data['openingHours'] ? $this->convertOpeningHoursToString($data['openingHours']) : null)
            ->setStoreHoursNotes($data['openingHoursNotes'])
            ->setDistribution(null) // XXX: This information is not returned by default and has to be fetched separately
            ->setParking($data['parkingOptions'])
            ->setBarrierFree($data['barrierFree'])
            ->setBonusCard($data['bonusCards'])
            ->setSection($data['sections'])
            ->setService($data['services'])
            ->setToilet($data['customerToilet']);
        return $store;
    }

    public function findImportById(int $companyId, int $importId): array
    {
        $response = $this->sendRequest('GET', '/api/imports/' . $importId)['body'];

        $import = [
            'id' => $this->convertIprotoIdToInt($response['@id']),
            'status' => $response['status'],
            'errors' => [],
        ];

        $jobId = preg_replace('#^.*?([^/]+)$#', '$1', $response['jobLog']['@id']); // "/api/jobs/8c3dbfe7-e9b0-4acb-95a5-eb715392b052" => "8c3dbfe7-e9b0-4acb-95a5-eb715392b052"
        if ($jobId) {
            $page = 1;
            $pageSize = 100;
            do {
                $response = $this->sendRequest('GET', '/api/logs', [
                    'commandId' => $jobId,
                    'level' => ['gt' => 399], // Only return messages worse than "warning"
                    'page' => $page++,
                    'itemsPerPage' => $pageSize,
                ]);
                $logs = $response['body']['hydra:member'];
                foreach ($logs as $log) {
                    if (array_key_exists('record', $log['extra'])) $record = (int)$log['extra']['record'];
                    else $record = null;
                    $import['errors'][] = [
                        'level' => $log['level'],
                        'levelName' => $log['levelName'],
                        'channel' => $log['channel'],
                        'timestamp' => $log['timestamp'],
                        'message' => $log['message'],
                        'record' => $record,
                    ];
                }
            } while (count($logs) == $pageSize);
        }

        return $import;
    }

    public function createBrochureImagesBatch(array $imageUrls): int
    {
        $response = $this->sendRequest('POST', '/api/image_batches',
            [],
            json_encode(['urls' => $imageUrls]),
            'application/json'
        )['body'];

        $batchId = $this->extractIdFromResourceString(filter_var($response['@id'], FILTER_SANITIZE_NUMBER_INT));

        return $batchId;
    }

    /**
     * Get all images for specific batch job id after the batch are done.
     */
    public function getBrochureImagesByBatchId(string $batchId, int $maxBatchWaitTime = 1024): array
    {
        try {
            // Call GET image_batches/{id} until status is 'done' with exponential backoff
            $waitTime = 1;
            $startTime = time();
            $numberOfFailedRequests = 0;

            while (true) {
                $currentRequestFailed = false;
                try {
                    $response = $this->sendRequest('GET', '/api/image_batches/' . $batchId);
                } catch (\Exception $exception) {
                    $numberOfFailedRequests++;
                    $currentRequestFailed = true;
                }

                if (!$currentRequestFailed) {
                    $decodedResponse = $response['body'];
                    $batchStatus = $decodedResponse['status'];
                    if ($batchStatus === 'done') {
                        break;
                    }
                }

                if (time() - $startTime > $maxBatchWaitTime) {
                    throw new \LogicException('image batch creation timed out');
                }
                sleep($waitTime);
                $waitTime *= 2;
            }

            $assets = $decodedResponse['assets'];
            foreach ($assets as $asset) {
                $imageUrlToImageIdMap[$asset['url']] = $asset['image']['@id'];
            }
        } catch (\Exception $exception) {
            throw new \LogicException('failed to create brochure images', 0, $exception);
        }

        return $imageUrlToImageIdMap;
    }

    private function extractIdFromResourceString(string $primaryIndustry): int
    {
        return (int) preg_replace('#^.*/(\d+)$#', '$1', $primaryIndustry);
    }

    public function createIntegration(array $integrationData): int
    {
        $response = $this->sendRequest('POST', '/api/integrations',
            [],
            json_encode($integrationData),
            'application/json'
        )['body'];

        return $this->extractIdFromResourceString(filter_var($response['@id'], FILTER_SANITIZE_NUMBER_INT));
    }
}
