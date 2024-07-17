<?php

require APPLICATION_PATH . '/../library/Marktjagd/Service/IprotoApi/IprotoApiClient.php';
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/New_Gen_Module.php';
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/New_Gen_Configuration.php';

/*
 * This service consumes products and customer specific configuration
 * and calculates the layout string for NewGen brochures
 *
 * The api contract between frontend and backend which is represented in
 * this algorithm is documented and discussed here:
 * https://gitlab.offerista.com/data-integration/newgen-json-schema
 */

class Blender
{
    /**
     * Loggingobjekt
     *
     * @var Zend_Log
     */
    protected $_logger;
    protected $layoutVersion = null;

    public static $availableModules;
    private $newGenConfiguration;

    /**
     * During class initialization, we load the available modules from the
     * git submodule newgen-api
     * @throws Exception
     */
    static function _init()
    {
        $newGenModulesFilePath = APPLICATION_PATH . '/../newgen-api/newgen_modules.json';
        if (file_exists($newGenModulesFilePath)) {
            $newGenModules = json_decode(file_get_contents($newGenModulesFilePath), true);
        } else {
            throw new Exception('../newgen-api/newgen_modules.json NOT AVAILABLE');
        }

        // Filtering product-modules for layout version 1
        $availableProductModules = [];
        foreach ($newGenModules['layoutVersions'][0]['categories'] as $productCategory) {
            if ($productCategory['category'] == 'products') {
                $availableProductModules = $productCategory['modules'];
            }
        }

        foreach ($availableProductModules as $availableProductModule) {
            self::$availableModules[] = new New_Gen_Module(
                $availableProductModule['name'],
                1,
                $availableProductModule['capacity'],
                $availableProductModule['highPriorityProducts'] ?? null
            );
        }
    }

    /**
     * Blender constructor.
     */
    public function __construct($companyId)
    {
        $this->_logger = Zend_Registry::get('logger');
        $this->newGenConfiguration = $this->getNewGenConfig($companyId);
    }

    private function getNewGenConfig($companyId)
    {
        return new New_Gen_Configuration($companyId);
    }

    public static function blendApi($companyId, $productPages, $brochureNumber = null, $environment = 'live'): array
    {
        $logger = Zend_Registry::get('logger');

        $c_id = (int)$companyId;
        if (empty($c_id)) {
            throw new Exception('company id parameter missing of empty');
        }

        if (gettype($productPages) !== 'array' or empty($productPages)) {
            throw new Exception('product pages parameter missing or empty');
        }

        if ($environment != 'live' and $environment != 'stage') {
            throw new Exception('environment parameter can only be "live" or "stage", provided was: ' . $environment);
        }

        if (!Blender::discoverConstraintValidation($productPages, $environment, $logger)) {
            throw new Exception('either to many products (live: 150, stage: 100) or product duplicates');
        }

        /*
         * Hotfix for renaming in Blender API
         *
         * The wording was changed in the API, and we were looking for a way to keep old Discover crawlers compatible.
         * Hence, we added this reformatting and replacing the old wording if present
         */
        foreach ($productPages as &$productPage) {
            # Replace 'page_metaphore' key with 'page_metaphor'
            if (isset($productPage['page_metaphore']) and !isset($productPage['page_metaphor']) ) {
                $productPage['page_metaphor'] = $productPage['page_metaphore'];
                unset($productPage['page_metaphore']);
                # if both keys are set, remove old 'page_metaphore' key
            } elseif (isset($productPage['page_metaphore']) and isset($productPage['page_metaphor'])) {
                unset($productPage['page_metaphore']);
            }
        }
        
        try {
            $requestBody = [
                'company_id' => $c_id,
                'pages' => $productPages
            ];

            if ($brochureNumber !== null and gettype($brochureNumber) === 'string' and empty($brochureNumber) === false) {
                $requestBody['brochure_number'] = $brochureNumber;
            }

            $config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/apiClient.ini', 'production');

            if ($environment == 'live') {
                $iprotoApiClient = new IprotoApiClient('production');
            } elseif ($environment == 'stage') {
                $iprotoApiClient = new IprotoApiClient('staging');
            } else {
                throw new Exception('environment parameter can only be "live" or "stage", provided was: ' . $environment);
            }

            $curlOpt = [
                CURLOPT_URL => $config->config->iproto->host . '/api/discover/blender',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_HTTPHEADER => ['Authorization:Bearer ' . $iprotoApiClient->getIprotoApiToken()],
                CURLOPT_POSTFIELDS => json_encode($requestBody)
            ];

            $ch = curl_init();

            $x = 1;
            $maxApiAttempts = 6;
            $response['http_code'] = 500;
            while ($x < $maxApiAttempts) {
                $logger->info('Blender API request attempt: ' . $x);
                sleep(pow($x, 2));
                curl_setopt_array($ch, $curlOpt);
                $response['body'] = curl_exec($ch);
                $response['http_code'] = curl_getinfo($ch)['http_code'];
                if ($response['http_code'] == 200) {
                    break;
                }

                $x++;
                $logger->warn("HTTP response code: " .$response['http_code']);
                $logger->warn($response['body']);

                if ($response['http_code'] == 401 && $response['body'] == '{"message":"Authentication Required"}') {
                    $logger->info('trying different authentication method');
                    $curlOpt[CURLOPT_HTTPHEADER] = ['X-AUTH-TOKEN:' . $config->config->iproto->secret_key];
                }

                $logger->warn('error during Blender API request, retry ' . $x . '/' . $maxApiAttempts);
            }

            if ($response['http_code'] != 200) {
                $response['error_message'] = ('ERROR requesting Discover layout from API - HTTP_CODE: ' . $response['http_code'] . ' --- ' . PHP_EOL . 'Response body: ' . PHP_EOL . $response['body']);
            }

            curl_close($ch);

            return $response;
        } catch (Exception $e) {
            $response['http_code'] = $e->getCode();
            $response['error_message'] = $e->getMessage();
            return $response;
        }
    }

    private static function discoverConstraintValidation($productPages, $environment): bool
    {
        $products = [];
        foreach ($productPages as $productPage) {
            foreach ($productPage['products'] as $product) {
                if (!array_key_exists($product['product_id'], $products)) {
                    $products[$product['product_id']] = true;
                }
            }
        }

        if ($environment == 'live' and count($products) <= 200) {
            return true;
        } elseif ($environment == 'stage' and count($products) <= 100) {
            return true;
        } else {
            return false;
        }
    }

    public function blend($productPages, $brochureNumber = null)
    {
        $outputPages = [
//            '1' => [
//                'pages' => []
//            ],
            '2' => [
                'ci' => $this->newGenConfiguration->customerCi->getCiAsArrayV2(),
                'pages' => []
            ],
            '3' => [
                'ci' => $this->newGenConfiguration->customerCi->getCiAsArrayV3(),
                'pages' => []
            ]
        ];

//        $this->blendV1($productPages, $outputPages['1']['pages']);
        $this->blendV2($productPages, $outputPages);
        $this->blendV3($productPages, $this->newGenConfiguration->customerAssets, $outputPages, $brochureNumber);

        return json_encode($outputPages);
    }

    private function fillModules(array $products, array $availableModules, array $blackListedModules, &$outputPages, $level = 0) {

        $level += 1;
        $this->_logger->debug('Recursion level ' . $level);
        $this->_logger->debug('Number of products left ' . count($products));

        if (count($products) == 0) {
            return true;
        }

        $chosenModule = $this->findTheRightModule($availableModules, $blackListedModules, $products);

        // Example for blacklisted modiles
        // $chosenModule = $this->findTheRightModule($availableModules, [$availableModules[0], $availableModules[1]], count($products));

        $poppedProducts = array_splice($products, 0, $chosenModule->capacity);
        $filledModule = $this->fillModule($poppedProducts, $chosenModule);

        array_push($outputPages, $filledModule);

        $this->fillModules($products, $availableModules, [$chosenModule], $outputPages, $level);

        return true;
    }

    private function fillModule(array $products, $module) {
        $filledModule = array(
            'name' => $module->name,
            'products' => array()
        );

        if ($module->highPriorityProducts != null) {
            // sorting products by prio if the module has high prio slots
            usort($products, function ($v) {
                return $v['prio'];
            });

            // first, fill the high prio slots with the highest prio products
            foreach ($module->highPriorityProducts as $highPriorityIndex) {
                $product = array_pop($products);
                $tmp['id'] =  $product['article_id'];
                if ($this->layoutVersion == 3) {
                    $tmp['priority'] = $product['prio'];
                }
                $filledModule['products'][$highPriorityIndex] = $tmp;
            }
        }

        // fill up the rest of the array
        foreach ($products as $product) {
            $idx = $this->findSmallestEmptyIndex($filledModule['products']);
            $tmp['id'] =  $product['article_id'];
            if ($this->layoutVersion == 3) {
                $tmp['priority'] = $product['prio'];
            }
            $filledModule['products'][$idx] = $tmp;
        }

        $filledModule['products'] = array_values($filledModule['products']);

        return $filledModule;
    }

    private function findTheRightModule(array $availableModules, array $blackListedModules, $products, string $seed = null) {

        foreach ($blackListedModules as $blackListedModule) {
            $key = array_search($blackListedModule, $availableModules);
            unset($availableModules[$key]);
        }
        $filteredModules = array_values($availableModules);

        // sorting filtered modules by capacity to easily remove modules with a capacity above the amount of products left
        usort($filteredModules, function ($v) {
            return $v->capacity;
        });

        // remove modules with a capacity higher than the amount of products left
        foreach ($filteredModules as $key => $filteredModule) {
            if ($filteredModule->capacity > count($products)) {
                unset($filteredModules[$key]);
            }
        }

        $filteredAndCleanedModules = array_values($filteredModules);

        // TODO We should check if the priority distribution of available products demands to filter modules with or without highPriority products
        // return the chosen module, either the biggest or based on a random index
        if ($seed != null) {
            mt_srand($seed);
        }

        if (count($filteredAndCleanedModules) == 0 ) {
            return new New_Gen_Module(
                'product_1_1',
                1,
                1,
                null
            );
        }

        return $filteredAndCleanedModules[mt_rand(0, count($filteredAndCleanedModules)-1)];
    }

    private function findSmallestEmptyIndex($products) {
        for ($x = 0; $x <= count($products); $x++) {
            if (!isset($products[$x]) or $products[$x] == null) {
                return $x;
            }
        }
    }

    /**
     * @param $productPages
     * @param array $outputPages
     */
    private function blendV1($productPages, array &$outputPages)
    {
        $this->_logger->info('Calculating Layout Version 1');
        $this->layoutVersion = 1;

        foreach ($productPages as $productPage) {
            $modules_1 = array();
            $modules_2 = array();
            $tmp = null;

            $counter = 0;
            foreach ($productPage['articles'] as $article) {
                if ($article['prio'] == 3) {
                    array_push($modules_1, array(
                            'type' => 'product_1_1',
                            'product' => array(
                                'id' => $article['article_id']
                            )
                        )
                    );
                } else {
                    if ($tmp == null) {
                        $tmp = array(
                            'type' => 'product_2_1',
                            'products' => array(
                                array(
                                    'id' => $article['article_id']
                                )
                            )
                        );
                    } elseif (is_array($tmp)) {
                        array_push($tmp['products'], array(
                            'id' => $article['article_id']
                        ));
                        array_push($modules_2, $tmp);
                        $tmp = null;
                    } else {
                        throw new Exception('FU');
                    }
                }
            }

            if (is_array($tmp)) {
                array_push($modules_1, array(
                        'type' => 'product_1_1',
                        'product' => array(
                            'id' => $tmp['products'][0]
                        )
                    )
                );
                $tmp = null;
            }

            $tmp2['modules'] = array_merge($modules_1, $modules_2);
            shuffle($tmp2['modules']);
            array_push($outputPages, $tmp2);
        }
    }

    /**
     * @param $productPages
     * @param array $outputPages
     */
    private function blendV2($productPages, array &$outputPages)
    {
        $this->_logger->info('Calculating Layout Version 2');
        $this->layoutVersion = 2;
        $pageNumber = 0;
        foreach ($productPages as $productPage) {
            $this->_logger->info('Calculating product page ' . $pageNumber);
            $this->_logger->info('This page has ' . count($productPage['articles']) . ' products');

            if (!empty($productPage['pageMetaphor'])) {
                $outputPages['2']['pages'][] = array(
                    'pageMetaphor' => $productPage['pageMetaphor'],
                    'modules' => array()
                );
            } else {
                $outputPages['2']['pages'][] = array(
                    'modules' => array()
                );
            }

            $this->fillModules($productPage['articles'], self::$availableModules, [], $outputPages['2']['pages'][$pageNumber]['modules']);
            $pageNumber += 1;
        }
    }

    private function blendV3($productPages, New_Gen_Customer_Assets $customerAssets, array &$outputPages, $brochureNumber)
    {
        $this->_logger->info('Calculating Layout Version 3');
        $this->layoutVersion = 3;
        $this->_logger->info('Filtering assets');

        $assets = $customerAssets->assets;
        $assetBrochureNumbers = array_map(function($v) {
            return $v['brochure_number'];
        }, $assets);

        // We only want default assets
        if (empty($brochureNumber)){
            $assets = array_filter($assets, function($v){
                if ($v['brochure_number'] == 'default') {
                    return true;
                } else {
                    return false;
                }
            });
        } elseif (in_array($brochureNumber, $assetBrochureNumbers)) {
            $assets = array_filter($assets, function($v) use($brochureNumber){
                if ($v['brochure_number'] == $brochureNumber) {
                    return true;
                } else {
                    return false;
                }
            });
        }

        $pageNumber = 0;
        foreach ($productPages as $productPage) {
            $this->_logger->info('Calculating product page ' . $pageNumber);
            $this->_logger->info('This page has ' . count($productPage['articles']) . ' products');

            if (!empty($productPage['pageMetaphor'])) {
                $outputPages['3']['pages'][] = array(
                    'pageMetaphor' => $productPage['pageMetaphor'],
                    'modules' => array()
                );
            } else {
                $outputPages['3']['pages'][] = array(
                    'modules' => array()
                );
            }

            $this->fillModules($productPage['articles'], self::$availableModules, [], $outputPages['3']['pages'][$pageNumber]['modules']);
            $pageNumber += 1;
        }

        $numberOfPages = count($productPages);
        if (!empty($assets)) {
            foreach ($assets as $asset) {
                $pageNumber = $asset['page'] - 1;
                $numberOfModulesOnPage = count($outputPages['3']['pages'][$pageNumber]['modules']);
                if ($pageNumber >= $numberOfPages) {
                    continue;
                }

                // If asset position is not set to 'top' or 'bottom', randomly assign a position
                if ($asset['position'] == 'random') {
                    if (rand(0, 1)) {
                        $asset['position'] = 'top';
                    } else {
                        $asset['position'] = 'bottom';
                    }
                }

                $assetModule = $this->generateAssetModule($asset);
                if ($asset['position'] == 'top') {
                    array_unshift($outputPages['3']['pages'][$pageNumber]['modules'], $assetModule);
                } elseif ($asset['position'] == 'bottom') {
                    $outputPages['3']['pages'][$pageNumber]['modules'][$numberOfModulesOnPage] = $assetModule;
                } else {
                    $this->_logger->err('Incompatible asset placement: ' . $asset['position']);
                }
            }
        }
    }

    private function generateAssetModule($asset)
    {
        $assetModule = [
            'name' => $asset['type']
        ];

        if ($assetModule['name'] == 'video_1') {
            $assetModule['videoThumbnailUrl'] = $asset['video_thumbnail_url'];
        }

        if (!empty($asset['clickout_url'])) {
            $assetModule['clickoutUrl'] = $asset['clickout_url'];
        }

        if (!empty($asset['source_url'])) {
            $assetModule['sourceUrl'] = $asset['source_url'];
        }

        return $assetModule;
    }
}

Blender::_init();
