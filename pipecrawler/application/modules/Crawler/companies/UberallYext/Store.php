<?php
/*
/*
 * Generic Store Crawler for Uberall/Yext
 *
 * The crawler fits into the normal DI-workflow, where it is started for a specific company
 * and the company_id is provided as a parameter.
 *
 * Based on the  provided company_id, the crawler finds out the correct third party integration
 * as well as the proper configuration
 *
 * This crawler currently imports stores for the following companies:
 * - Kaufland (Uberall - csv)
 * id	identifier	status	name	address_display	street_name	street_no	lattitude	longitude	email	long_description	short_description	zip	city	country	phones	website	openingHours	opening_hours_notes	categories	payment_options	services
*/

require_once APPLICATION_PATH . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Sts\StsClient;
use Aws\S3\ObjectUploader;
use Aws\S3\Exception\S3Exception;
use Aws\Credentials\CredentialProvider;
use Marktjagd\ApiClient\Resource;
use Marktjagd\Service\IprotoApi\ApiServiceProvider;

class Crawler_Company_UberallYext_Store extends Crawler_Generic_Company
{
    const YEXT = 'yext';
    const UBERALL = 'uberall';

    /*
     * This could be extracted into a separate configu object/class,
     * but since we plan to integrate all companies from active third parties,
     * we should need this specific configuration in the future
     * and all company-specific methods should be placed in specific services / classes.
     */
    private $THIRD_PARTY_CONFIG = [
        self::YEXT => [
            'datasource_config' => [
                'datasource' => 's3',
                'region' => 'eu-west-1',
                'bucket' => 'yext-offerista-feed',
                'roleArn' => 'arn:aws:iam::385750204895:role/yext-s3-access.di-prod'
            ],
            'companies' => [
                29 => [
                    'name' => 'ALDI Süd',
                    'country' => 'DE',
                    'folder' => 'marktjagd_de_feeds/',
                    'file' => 'listings_<date(Y-m-d)>.json',
                    'environment' => 'production',
                    // Necessary because $cCrawlerConfigs = $sCrawlerConfig->findByCompanyTypeStatus($companyId, 'stores', 'zeitgesteuert'); has a bug
                    'idCrawlerConfig' => 553
                ],
                122 => [
                    'name' => 'PENNY',
                    'country' => 'DE',
                    'folder' => 'marktjagd_de_feeds/',
                    'file' => 'listings_<date(Y-m-d)>.json',
                    'environment' => 'production',
                    // Necessary because $cCrawlerConfigs = $sCrawlerConfig->findByCompanyTypeStatus($companyId, 'stores', 'zeitgesteuert'); has a bug
                    'idCrawlerConfig' => 47
                ],
//                186 => [
//                    'name' => 'Polo Motorrad',
//                    'country' => 'DE',
//                    'folder' => 'marktjagd_de_feeds/',
//                    'file' => 'listings_<date(Y-m-d)>.json',
//                    'environment' => 'production',
//                    // Necessary because $cCrawlerConfigs = $sCrawlerConfig->findByCompanyTypeStatus($companyId, 'stores', 'zeitgesteuert'); has a bug
//                    'idCrawlerConfig' => 52
//                ],
                28675 => [
                    'name' => 'Apple',
                    'country' => 'DE',
                    'folder' => 'marktjagd_de_feeds/',
                    'file' => 'listings_<date(Y-m-d)>.json',
                    'environment' => 'production',
                    // Necessary because $cCrawlerConfigs = $sCrawlerConfig->findByCompanyTypeStatus($companyId, 'stores', 'zeitgesteuert'); has a bug
                    'idCrawlerConfig' => 1665
                ],
                28895 => [
                    'name' => 'Vodafone',
                    'country' => 'DE',
                    'folder' => 'marktjagd_de_feeds/',
                    'file' => 'listings_<date(Y-m-d)>.json',
                    'environment' => 'production',
                    // Necessary because $cCrawlerConfigs = $sCrawlerConfig->findByCompanyTypeStatus($companyId, 'stores', 'zeitgesteuert'); has a bug
                    'idCrawlerConfig' => 778
                ],
                77275 => [
                    'name' => 'Media Markt',
                    'country' => 'DE',
                    'folder' => 'marktjagd_de_feeds/',
                    'file' => 'listings_<date(Y-m-d)>.json',
                    'environment' => 'stage',
                    // Necessary because $cCrawlerConfigs = $sCrawlerConfig->findByCompanyTypeStatus($companyId, 'stores', 'zeitgesteuert'); has a bug
                    'idCrawlerConfig' => 1635
                ],
                77556 => [
                    'name' => 'Danas Schloss',
                    'country' => 'AT',
                    'folder' => 'wogibtswas_at_feeds/',
//                    'file' => 'listings_<date(Y-m-d)>.json',
                    'file' => 'listings_2021-01-20.json',
                    'environment' => 'stage',
                    // Necessary because $cCrawlerConfigs = $sCrawlerConfig->findByCompanyTypeStatus($companyId, 'stores', 'zeitgesteuert'); has a bug
                    // TODO: Company still missing in DI GUI, HENCE, WE CANT CREATE A CRAWLER CONFIG YET
                    // 'idCrawlerConfig' => 1635
                ],
                73424 => [
                    'name' => 'dm drogerie markt',
                    'country' => 'AT',
                    'folder' => 'wogibtswas_at_feeds/',
                    'file' => 'listings_<date(Y-m-d)>.json',
                    'environment' => 'production',
                    // Necessary because $cCrawlerConfigs = $sCrawlerConfig->findByCompanyTypeStatus($companyId, 'stores', 'zeitgesteuert'); has a bug
                    'idCrawlerConfig' => 1563
                ],
            ]
        ],
        self::UBERALL => [
            'datasource_config' => [
                'datasource' => 'ftp',
                'filetype' => 'csv',
                'filename' => 'locations.csv'
            ],
            'companies' => [
                67394 => [
                    'name' => 'Kaufland',
                    'country' => 'DE',
                    'status_closed' => 'CLOSED',
                    'status_open' => 'ACTIVE',
                    'environment' => 'production'
                ],
                77274 => [
                    'name' => 'Kaufland',
                    'country' => 'DE',
                    'status_closed' => 'CLOSED',
                    'status_open' => 'ACTIVE',
                    'environment' => 'stage'
                ]
            ]
        ]
    ];

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Zend_Exception
     * @throws Exception
     */
    public function crawl($companyId): Crawler_Generic_Response
    {
        $this->_logger->info('Starting generic Uberall/Yext Store crawler for Company ID: ' . $companyId);
        $necessaryThirdPartyIntegration = $this->findingThirdPartyIntegration($companyId);
        $dataSource = $this->getThirdPartyStoreDataSource($necessaryThirdPartyIntegration, $companyId);
        $cStores = $this->mapStoreData($necessaryThirdPartyIntegration, $dataSource, $companyId);

        if ($necessaryThirdPartyIntegration == self::YEXT) {
            return $this->importYextStoresAndCreateReceipt($cStores, $companyId);
        } elseif ($necessaryThirdPartyIntegration == self::UBERALL) {
            return $this->getResponse($cStores, $companyId);
        } else {
            throw new Exception('Unexpected reference to third party:' . $necessaryThirdPartyIntegration);
        }
    }

    /**
     * This function is looking for the passed $companyId inside the configuration arrays
     * to decide which third party integration is necessary to crawl the locations of this
     * specific sompany.
     *
     * @param $companyId
     * @return string
     * @throws Exception
     */
    private function findingThirdPartyIntegration($companyId): string
    {
        if (array_key_exists($companyId, $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'])) {
            $this->_logger->info('Company FOUND in yext config: ' . $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['name']);
            return self::YEXT;
        } elseif (array_key_exists($companyId, $this->THIRD_PARTY_CONFIG[self::UBERALL]['companies'])) {
            $this->_logger->info('Company FOUND in uberall config: ' . $this->THIRD_PARTY_CONFIG[self::UBERALL]['companies'][$companyId]['name']);
            return self::UBERALL;
        } else {
            throw new Exception('Company: ' . $companyId . ' neither found in Yext nor Uberall config.');
        }
    }

    /**
     * Wrapper-function for the specific third party downloads.
     *
     * @param string $necessaryThirdPartyIntegration
     * @param $companyId
     * @return array
     * @throws Exception
     */
    private function getThirdPartyStoreDataSource(string $necessaryThirdPartyIntegration, $companyId): array
    {
        if ($necessaryThirdPartyIntegration == self::YEXT) {
            return $this->getYextStoreDataSource($companyId);
        } elseif ($necessaryThirdPartyIntegration == self::UBERALL) {
            return $this->getUberallStoreDataSource();
        } else {
            throw new Exception('Unexpected reference to third party store data source:' . $necessaryThirdPartyIntegration);
        }
    }

    /**
     * Downloads the store json file provided by Yext from their S3 bucket
     * @param $companyId
     * @return array
     * @throws Exception
     */
    private function getYextStoreDataSource($companyId): array
    {
        $provider = CredentialProvider::instanceProfile();
        $memoizedProvider = CredentialProvider::memoize($provider);

        $stsClient = new Aws\Sts\StsClient([
            'region' => $this->THIRD_PARTY_CONFIG[self::YEXT]['datasource_config']['region'],
            'version' => '2011-06-15',
            'credentials' => $memoizedProvider
        ]);

        $result = $stsClient->AssumeRole([
            'RoleArn' => $this->THIRD_PARTY_CONFIG[self::YEXT]['datasource_config']['roleArn'],
            'RoleSessionName' => 'Offerista-File_Download',
        ]);

        $s3Client = (new \Aws\Sdk)->createMultiRegionS3([
            'version' => '2006-03-01',
            'credentials' => [
                'key' => $result['Credentials']['AccessKeyId'],
                'secret' => $result['Credentials']['SecretAccessKey'],
                'token' => $result['Credentials']['SessionToken']
            ]
        ]);

        $bucketObjects = $s3Client->listObjects(array(
            'Bucket' => 'yext-offerista-feed',
            'Prefix' => $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['folder']
        ));

        $fileFound = false;
        $file = $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['folder']
            . preg_replace('#<date\(Y-m-d\)>#', date("Y-m-d", strtotime("yesterday")), $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['file']);
        foreach ($bucketObjects['Contents'] as $object) {
            if ($object['Key'] == $file) {
                $fileFound = true;
                break;
            }
        }

        if ($fileFound == false) {
            $this->_logger->err('ERROR finding Yext feed for ' . $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['name']);
            $this->_logger->err('Was looking for file: ' . $file);
            $this->_logger->err('Available files: ');
            foreach ($bucketObjects['Contents'] as $object) {
                $this->_logger->err($object['Key']);
            }
            throw new Exception('ERROR finding Yext feed!');
        }

        $object = $s3Client->getObject(array(
            'Bucket' => 'yext-offerista-feed',
            'Key' => $file
        ));

        $body = $object['Body']->getContents();

        $separator = "\r\n";
        $line = strtok($body, $separator);

        $pattern = '#' . $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['name'] . '#i';
        $stores = array();
        while ($line !== false) {
            $store = json_decode($line, true);
            if (key_exists('chain', $store) and preg_match($pattern, $store['chain'])) {
                array_push($stores, $store);
            } elseif (key_exists('name', $store) and preg_match($pattern, $store['name'])) {
                array_push($stores, $store);
            }
            $line = strtok($separator);
        }

        if (count($stores) == 0) {
            throw new Exception('ERROR: No store found for ' . $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['name']);
        }
        return $stores;
    }

    /**
     * Downloads the store csv file provided by uberall from our ftp server
     * @return array
     * @throws Exception
     */
    private function getUberallStoreDataSource(): array
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->_logger->info('Getting the store csv file from uberall...');

        $this->_logger->info('Trying to connect to FTP server');
        $sFtp->connect();
        $this->_logger->info('CONNECTED!');

        $this->_logger->info('Changing dir to ' . self::UBERALL);
        $sFtp->changedir(self::UBERALL);

        $storeCsvFile = null;
        foreach ($sFtp->listFiles() as $file) {
            if (preg_match('#' . $this->THIRD_PARTY_CONFIG[self::UBERALL]['datasource_config']['filename'] . '#', $file)) {
                $storeCsvFile = $sFtp->downloadFtpToCompanyDir($file, self::UBERALL);
                break;
            }
        }

        if ($storeCsvFile == null) {
            throw new Exception('UNABLE to find store csv file!');
        }

        $this->_logger->info('FOUND store csv file!');
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $data = $sPss->readFile($storeCsvFile, true)->getElement(0)->getData();
        if (count($data) == 0) {
            throw new Exception('No stores in uberall file.');
        }
        return $data;
    }

    /**
     * Maps the store data from the given csv file to marktjagd api stores based on the given third party config
     * @param string $necessaryThirdPartyIntegration
     * @param array $dataSource
     * @param $comanyId
     * @return Marktjagd_Collection_Api_Store|void
     * @throws Exception
     */
    private function mapStoreData(string $necessaryThirdPartyIntegration, array $dataSource, string $comanyId): Marktjagd_Collection_Api_Store
    {
        if ($necessaryThirdPartyIntegration == self::YEXT) {
            return $this->mapYextStoreData($dataSource);
        } elseif ($necessaryThirdPartyIntegration == self::UBERALL) {
            return $this->mapUberallStoreData($dataSource, $comanyId);
        } else {
            throw new Exception('Unexpected reference to third party store csv file.');
        }
    }

    /**
     * Mapping the content of Yext's store csv file to marktjagd api stores
     * @param array $dataSource
     * @return Marktjagd_Collection_Api_Store
     * @throws Exception
     */
    private function mapYextStoreData(array $dataSource): Marktjagd_Collection_Api_Store
    {
        $this->_logger->info('Mapping yext stores');
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($dataSource as $store) {
            if (array_key_exists('closed', $store)) {
                if ($store['closed'] == true) {
                    continue;
                }
            } elseif (array_key_exists('status', $store)) {
                if ($store['status'] == 'INACTIVE') {
                    continue;
                }
            } else {
                $this->_logger->info('Array key: status did not exist for store with yextId: ' . $store['yextId']);
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            if (array_key_exists('phones', $store)) {
                foreach ($store['phones'] as $number) {
                    if ($number['type'] == 'MAIN') {
                        $eStore->setPhoneNormalized($number['number']['number']);
                    } elseif ($number['type'] == 'FAX') {
                        $eStore->setFaxNormalized($number['number']['number']);
                    }
                }
            } else {
                $this->_logger->info('Array key: phones did not exist for store with yextId: ' . $store['yextId']);
            }

            if (array_key_exists('emails', $store)) {
                $emailTmp = array_pop($store['emails']);
                if (gettype($emailTmp) == 'array' and array_key_exists('address', $emailTmp) and gettype($emailTmp['address'] == 'string')) {
                    $eStore->setEmail($emailTmp['address']);
                } else {
                    $this->_logger->info('Array key: emails did not exist for store with yextId: ' . $store['yextId']);
                }
            } else {
                $this->_logger->info('Array key: emails did not exist for store with yextId: ' . $store['yextId']);
            }

            if (array_key_exists('urls', $store)) {
                foreach ($store['urls'] as $url) {
                    if ($url['type'] == 'WEBSITE') {
                        $eStore->setWebsite(preg_replace('#http://#', 'https://', $url['url']));
                        break;
                    }
                }
            } else {
                $this->_logger->info('Array key: urls did not exist for store with yextId: ' . $store['yextId']);
            }

            if (array_key_exists('description', $store)) {
                $eStore->setText($store['description']);
            } else {
                $this->_logger->info('Array key: description did not exist for store with yextId: ' . $store['yextId']);
            }

            if (array_key_exists('hours', $store)) {
                $storeHours = '';
                foreach ($store['hours'] as $day) {
                    foreach ($day['intervals'] as $interval) {
                        $storeHours = $storeHours . $day['day'] . ': ' . $interval['start'] . '-' . $interval['end'] . ', ';
                    }
                }
                $eStore->setStoreHoursNormalized($storeHours);
            } elseif (array_key_exists('reopenDate', $store)) {
                if ($store['reopenDate'] != '') {
                    $eStore->setText('Wiedereröffnung: ' . $store['reopenDate'] . '\n' . $eStore->getText());
                }
            } else {
                $this->_logger->info('Array key: hours did not exist for store with yextId: ' . $store['yextId']);
            }

            if (array_key_exists('paymentOptions', $store)) {
                if (gettype($store['paymentOptions'] == 'array')) {
                    $eStore->setPayment(implode(', ', $store['paymentOptions']));
                }
            } else {
                $this->_logger->info('Array key: paymentOptions did not exist for store with yextId: ' . $store['yextId']);
            }

            if (array_key_exists('images', $store)) {
                foreach ($store['images'] as $image) {
                    if ($image['type'] == 'LOGO') {
                        $eStore->setLogo($image['url']);
                    } elseif ($image['type'] == 'IMAGE' and $eStore->getImage() == '') {
                        $eStore->setImage($image['url']);
                    }
                }
            } else {
                $this->_logger->info('Array key: images did not exist for store with yextId: ' . $store['yextId']);
            }

            if (array_key_exists('services', $store)) {
                $eStore->setService(implode(', ', $store['services']));
            } else {
                $this->_logger->info('Array key: services did not exist for store with yextId: ' . $store['yextId']);
            }

            if (array_key_exists('hoursText', $store)) {
                if (array_key_exists('additional', $store['hoursText'])) {
                    if ($store['hoursText']['additional'] != 'Oeffnungszeiten') {
                        $eStore->setStoreHoursNotes($store['hoursText']['additional']);
                    }
                }
            } else {
                $this->_logger->info('Array key: hoursText did not exist for store with yextId: ' . $store['yextId']);
            }

            if (array_key_exists('name', $store)) {
                $eStore->setTitle($store['name']);
            } else {
                $this->_logger->info('Array key: name did not exist for store with yextId: ' . $store['yextId']);
            }

            /*
             * Only for testing purposes, we switched to the yextId instead of using the partnerId
             */
            if (array_key_exists('yextId', $store)) {
                $eStore->setStoreNumber($store['yextId']);
            } else {
                $this->_logger->info('Array key: yextId did not exist for store');
            }

            $eStore->setStreetAndStreetNumber($store['address']['address']);
            $eStore->setCity($store['address']['city']);
            $eStore->setZipcode($store['address']['postalCode']);
            $eStore->setLatitude($store['geoData']['displayLatitude']);
            $eStore->setLongitude($store['geoData']['displayLongitude']);

            if (!$cStores->addElement($eStore)) {
                $this->_logger->error('Was not able to add store to collection');
                var_dump($eStore);
            }
        }
        return $cStores;
    }

    /**
     * Mapping the content of Uberall's store csv file to marktjagd api stores
     * @param array $dataSource
     * @param string $companyId
     * @return Marktjagd_Collection_Api_Store
     */
    private function mapUberallStoreData(array $dataSource, string $companyId): Marktjagd_Collection_Api_Store
    {
        if ($companyId == 67394) {
            $aStores = $this->findSalesRegions($companyId);
        }
        $this->_logger->info('The initial data source contains ' . count($dataSource) . ' entries');
        $this->_logger->info('Filtering uberall stores based on company configuration for: ' . $companyId . ' - ' . $this->THIRD_PARTY_CONFIG[self::UBERALL]['companies'][$companyId]['name']);

        $storesFiltered = array_filter($dataSource, function ($v, $k) use ($companyId) {
            return
                preg_match('#' . $this->THIRD_PARTY_CONFIG[self::UBERALL]['companies'][$companyId]['name'] . '#i', $v['name']) and
                preg_match('#' . $this->THIRD_PARTY_CONFIG[self::UBERALL]['companies'][$companyId]['country'] . '#i', $v['country']) and
                preg_match('#' . $this->THIRD_PARTY_CONFIG[self::UBERALL]['companies'][$companyId]['status_open'] . '#i', $v['status']);
        }, ARRAY_FILTER_USE_BOTH);

        $this->_logger->info('The filtered data source contains ' . count($storesFiltered) . ' entries');

        $this->_logger->info('Mapping uberall stores');
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storesFiltered as $store) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber(preg_replace('#DE#', '', $store['identifier']))
                ->setStreet($store['street_name'])
                ->setStreetNumber(strval($store['street_no']))
                ->setZipcode(strval($store['zip']))
                ->setCity($store['city'])
                ->setLatitude(strval($store['lattitude']))
                ->setLongitude(strval($store['longitude']))
                ->setEmail($store['email'])
                ->setPhone($store['phones'])
                ->setWebsite($store['website'])
                ->setText($store['short_description'])
                ->setService(preg_replace('#;#', ',', $store['services']))
                ->setStoreHoursNormalized($store['openingHours']);

            if ($store['opening_hours_notes'] != 'None' && $store['opening_hours_notes'] != 'none') {
                $eStore->setStoreHoursNotes($store['opening_hours_notes']);
            }

            if (in_array($eStore->getStoreNumber(), $aStores)) {
                $eStore->setDistribution('Mitsuba');
            }

            if (!$cStores->addElement($eStore)) {
                $this->_logger->info('Was not able to add store to collection');
                var_dump($eStore);
            }
        }
        return $cStores;
    }

    private function importYextStoresAndCreateReceipt($cStores, int $companyId): Crawler_Generic_Response
    {
        $sApi = new Marktjagd_Service_Output_MarktjagdApi();

        $sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
        $idCrawlerConfig = $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['idCrawlerConfig'];
        $cCrawlerConfigs = $sCrawlerConfig->findById($idCrawlerConfig);

        $response = $this->getResponse($cStores, $companyId);

        $crawler_Generic_Response = $sApi->import($cCrawlerConfigs, $response);

        $importId = $crawler_Generic_Response->getImportId();
        if ($crawler_Generic_Response->getLoggingCode() == Crawler_Generic_Response::IMPORT_FAILURE_ADD) {
            $this->_logger->err('Error during import: ' . $importId);
            return $response;
        } else {
            $import = $this->waitForImportToFinish($companyId, $importId);
            if ($import == null) {
                $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE);
                return $response;
            } else {
                return $this->createReceiptAndReturnResponse($response, $importId, $import, $companyId, $cStores);
            }
        }
    }

    private function waitForImportToFinish(int $companyId, int $importId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $i = 0;
        do {
            $i += 1;
            if ($i >= 1200) {
                $this->_logger->err('Error during import - timeout for import: ' . $importId);
                return null;
            }

            $import = $sApi->findImportById($companyId, $importId);
            $importState = (string) $import['status'];
            sleep(10);
        } while (!in_array($importState, ['skipped', 'done', 'failed']));

        return $import;
    }

    private function createReceiptAndReturnResponse(Crawler_Generic_Response $response, $importId, $import, $companyId, $cStores): Crawler_Generic_Response
    {
        $importState = (string)$import['status'];
        if ($importState == 'done') {
            $ret = $this->createReceipt($response, $import, $companyId, $cStores);
            if ($ret == null) {
                $this->_logger->err('Error during receipt creation for import: ' . $importId);
                $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE);
            } else {
                $this->_logger->info('Receipt created successfully for import: ' . $importId);
                $response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            }
        } elseif ($importState == 'failed') {
            $this->_logger->err('Error during import: ' . $importId);
            $this->createReceiptWithErrorMessages($response, $import, $companyId, $cStores);
            $this->createReceipt($response, $import, $companyId, $cStores);
            $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE);
        } elseif ($importState == 'skipped') {
            $this->_logger->info('Import: ' . $importId . ' was skipped!');
            $response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
        } else {
            $this->_logger->err('UNKNOWN state: ' . $importState . ' for import: ' . $importId);
            $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE);
        }
        $response->setIsImport(FALSE);
        return $response;
    }

    private function createReceipt(Crawler_Generic_Response $response, $import, $companyId, $cStores): ?Crawler_Generic_Response
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $apiStores = $sApi->findAllStoresForCompany($companyId, 100000);

        if (gettype($apiStores) == 'bool') {
            $this->_logger->err('No stores found over API!');
            return null;
        }

        $receipt = '';
        foreach ($cStores->getElements() as $cStore) {
            $receipt = $receipt . $this->createReceiptEntry($import, $cStore, $apiStores, $companyId) . PHP_EOL;
        }

        $ret = $this->uploadReceipt($receipt, $companyId, $cStores);
        if ($ret == true) {
            $this->_logger->info("Receipt uploaded");
            $response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            return $response;
        } else {
            $this->_logger->err('Error during receipt upload');
            $this->_logger->err($ret);
            return null;
        }
    }

    private function createReceiptWithErrorMessages(Crawler_Generic_Response $response, $import, $companyId, $cStores): ?Crawler_Generic_Response
    {
        $receipt = '';
        $keys = array_keys($cStores->getElements());
        foreach ($import['errors'] as $importError) {
            $yextId = $keys[$importError['record']];
            $receipt = $receipt . $this->createErrorReceiptEntry($import, $importError, $yextId) . PHP_EOL;
        }

        $ret = $this->uploadReceipt($receipt, $companyId, $cStores);
        if ($ret == true) {
            $this->_logger->info("Receipt with error messages uploaded");
            $response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            return $response;
        } else {
            $this->_logger->err('Error during receipt upload');
            $this->_logger->err($ret);
            return null;
        }
    }

    private function createErrorReceiptEntry($import, $importError, $yextId)
    {
        $receiptEntry = [];
        $receiptEntry['offeristaImportId'] = $import['id'];
        $receiptEntry['storeNumber'] = $yextId;
        $receiptEntry['importState'] = 'error';
        $receiptEntry['message'] = $importError['message'];
        $receiptEntry['timestamp'] = date("Y-m-d h:i:sa");
        return json_encode($receiptEntry);
    }

    private function createReceiptEntry($import, $cStore, &$apiStores, $companyId)
    {
        $receiptEntry = [];
        $receiptEntry['offeristaImportId'] = $import['id'];
        $receiptEntry['storeNumber'] = $cStore->getStoreNumber();

        $storeId = null;
        $apiStores = array_filter($apiStores, function ($v, $k) use (&$receiptEntry, &$storeId) {
            if ($v['number'] == $receiptEntry['storeNumber']) {
                $storeId = $k;
                return false;
            } else {
                return true;
            }
        }, ARRAY_FILTER_USE_BOTH);

        if ($storeId == null) {
            $this->_logger->err('No stores found over Offerista API for storeNumber / yextId:' . $receiptEntry['storeNumber']);
            $receiptEntry['importState'] = 'error';
            $receiptEntry['message'] = 'Store was not imported. No stores found over Offerista API for storeNumber / yextId:' . $receiptEntry['storeNumber'];
        } else {
            $receiptEntry['offeristaStoreId'] = $storeId;
            $receiptEntry['importState'] = 'success';
            if ($this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['country'] == 'DE') {
                $receiptEntry['url'] = $this->createMarktjagdStoreUrl($cStore, $storeId, $companyId);
            } elseif ($this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['country'] == 'AT') {
                $receiptEntry['url'] = $this->createWogibtswasStoreUrl($cStore, $storeId, $companyId);
            }
            if ($receiptEntry['url'] == null) {
                $receiptEntry['importState'] = 'error';
                $receiptEntry['message'] = 'Error during URL creation, some necessary urls are missing.';
            }
        }

        $receiptEntry['timestamp'] = date("Y-m-d h:i:sa");
        return json_encode($receiptEntry);
    }

    private function uploadReceipt(string $receipt, $companyId, $cStores)
    {
        $this->_logger->info('Uploading receipt!');

        try {
            $provider = CredentialProvider::instanceProfile();
            $memoizedProvider = CredentialProvider::memoize($provider);

            $stsClient = new Aws\Sts\StsClient([
                'region' => $this->THIRD_PARTY_CONFIG[self::YEXT]['datasource_config']['region'],
                'version' => '2011-06-15',
                'credentials' => $memoizedProvider
            ]);

            $result = $stsClient->AssumeRole([
                'RoleArn' => $this->THIRD_PARTY_CONFIG[self::YEXT]['datasource_config']['roleArn'],
                'RoleSessionName' => 'Offerista-File_Download',
            ]);

            $s3Client = (new \Aws\Sdk)->createMultiRegionS3([
                'version' => '2006-03-01',
                'credentials' => [
                    'key' => $result['Credentials']['AccessKeyId'],
                    'secret' => $result['Credentials']['SecretAccessKey'],
                    'token' => $result['Credentials']['SessionToken']
                ]
            ]);

            $receiptAlreadyExists = $s3Client->doesObjectExist('yext-offerista-feed', $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['folder'] . 'receipt_listings_' . date('Y-m-d') . '.json');

            if ($receiptAlreadyExists == false) {
                $result = $s3Client->putObject([
                    'Bucket' => 'yext-offerista-feed',
                    'Key' => $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['folder'] . 'receipt_listings_' . date('Y-m-d') . '.json',
                    'Body' => $receipt,
                    'ACL' => 'public-read'
                ]);
                $this->_logger->info('Receipt s3 key:' . $result['ObjectURL']);
            } else {
                $object = $s3Client->getObject(array(
                    'Bucket' => 'yext-offerista-feed',
                    'Key' => $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['folder'] . 'receipt_listings_' . date('Y-m-d') . '.json'
                ));

                $body = $object['Body']->getContents();

                $newBody = $this->createNewAndOverrideExistingReceiptEntries($body, $receipt, $cStores);

                $result = $s3Client->putObject([
                    'Bucket' => 'yext-offerista-feed',
                    'Key' => $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['folder'] . 'receipt_listings_' . date('Y-m-d') . '.json',
                    'Body' => $newBody . PHP_EOL,
                    'ACL' => 'public-read'
                ]);
                $this->_logger->info('Receipt s3 key:' . $result['ObjectURL']);
            }

            $object = $s3Client->getObject(array(
                'Bucket' => 'yext-offerista-feed',
                'Key' => $this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['folder'] . 'receipt_listings_' . date('Y-m-d') . '.json'
            ));

        } catch (S3Exception $e) {
            echo $e->getMessage();
            return (string)$e->getMessage();
        }

        return true;
    }

    private function createMarktjagdStoreUrl($cStore, $storeId, $companyId): ?string
    {
        if ($this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['environment'] == 'production') {
            // https://www.marktjagd.de/berlin/filiale/lidl-charlottenstrasse:198272
            $url = 'https://www.marktjagd.de/f/';
        } else {
            // https://marktjagd-de.legacy-portal-elb.frontend-stage.offerista.com/berlin/filiale/scoops-ahoy-eiscreme-rykestrasse:997828
            $url = 'https://marktjagd-de.legacy-portal-elb.frontend-stage.offerista.com/';
        }

        return $this->createStoreUrl($cStore, $storeId, $url);
    }

    private function createBarcooStoreUrl($cStore, $storeId, $companyId): ?string
    {
        if ($this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['environment'] == 'production') {
            // https://www.wogibtswas.at/f/1155177-billa-zwettlerstrasse-10-3902-vitis
            $url = 'https://www.barcoo.de/f/';
        } else {
            // https://barcoo-de.portal-elb.frontend-stage.offerista.com/f/997828-scoops-ahoy-eiscreme-rykestrasse-40-10405-berlin
            $url = 'https://barcoo-de.portal-elb.frontend-stage.offerista.com/f/';
        }

        return $this->createStoreUrl($cStore, $storeId, $url);
    }

    private function createWogibtswasStoreUrl($cStore, $storeId, $companyId): ?string
    {
        if ($this->THIRD_PARTY_CONFIG[self::YEXT]['companies'][$companyId]['environment'] == 'production') {
            // https://www.barcoo.de/f/609178-e-center-provianthofstrasse-3-01099-dresden
            $url = 'https://www.wogibtswas.at/f/';
        } else {
            // https://wogibtswas-at.portal-elb.frontend-dev.offerista.com/f/951734-hofer-atterseestrasse-95-4850-timelkam
            $url = 'https://wogibtswas-at.portal-elb.frontend-dev.offerista.com//f/';
        }

        return $this->createStoreUrl($cStore, $storeId, $url);
    }

    private function createStoreUrl($cStore, $storeId, $url): ?string
    {
        $storeUrl = $storeId . '-' . $cStore->getTitle() . '-' . $cStore->getStreet() . '-' . $cStore->getStreetNumber() . '-' . $cStore->getZipcode() . '-' . $cStore->getCity();
        $sluggified = $this->slugify($storeUrl);

        if ($sluggified == null) {
            return null;
        }

        return $url . $sluggified;
    }

    public function slugify(string $string)
    {
        $systemLocale = setlocale(LC_ALL, 0);
        setlocale(LC_ALL, 'de_DE');

        $slug = preg_replace(['#ä#', '#ö#', '#ü#', '#ß#'], ['a', 'o', 'u', 'ss'], $string);
        $slug = iconv("UTF-8", "ASCII//TRANSLIT", $slug);
        if ($slug == false) {
            $this->_logger->err('Error during URL creation, Character encoding to ASCII failed!');
            return null;
        }

        $slug = preg_replace('#[^A-Za-z0-9-]+#', '-', $slug);
        $slug = preg_replace('#-+#', '-', $slug);
        $slug = trim($slug, '-');
        $slug = strtolower($slug);
        setlocale(LC_ALL, $systemLocale);
        return substr($slug, 0, 1950);
    }

    private function createNewAndOverrideExistingReceiptEntries($body, string $receipt, $cStores): string
    {
        $oldBodyFiltered = '';

        $storeNumbers = array_keys($cStores->getElements());

        $separator = "\r\n";
        $line = strtok($body, $separator);
        while ($line !== false) {
            $store = json_decode($line, true);
            $storeNumberInReceipt = $store['storeNumber'];
            if (!in_array($storeNumberInReceipt, $storeNumbers)) {
                $oldBodyFiltered .= $line . PHP_EOL;
            }
            $line = strtok($separator);
        }
        return $oldBodyFiltered . PHP_EOL . $receipt;
    }

    private function findSalesRegions($companyId): ?array
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $localPath = $sFtp->connect($companyId, TRUE);
        $localCampaignFile = $sFtp->downloadFtpToDir('Kaufland-stores-Mitsuba.xlsx', $localPath);

        $aData = $sPss->readFile($localCampaignFile, TRUE)->getElement(0)->getData();

        $aSpecialStores = [];
        foreach ($aData as $singleRow) {
            $aSpecialStores[] = $singleRow['Betrieb'];
        }

        return $aSpecialStores;
    }
}
