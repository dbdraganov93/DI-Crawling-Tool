<?php

require_once APPLICATION_PATH . '/../vendor/autoload.php';
require APPLICATION_PATH . '/../library/Marktjagd/Service/IprotoApi/IprotoApiClient.php';

use Aws\S3\S3Client;
use Aws\Sts\StsClient;
use Aws\S3\ObjectUploader;
use Aws\S3\Exception\S3Exception;
use Aws\Credentials\CredentialProvider;
use Marktjagd\ApiClient\Resource;
use Marktjagd\Service\IprotoApi\ApiServiceProvider;

class YextApi
{
    /**
     * Loggingobjekt
     *
     * @var Zend_Log
     */
    protected $_logger;
    private $companyId;
    private $environment;
    /**
     * @var IprotoApiClient
     */
    private $iprotoApiClient;

    /**
     * YextApi constructor.
     * @param string $companyId
     * @param $environment
     * @throws Zend_Exception
     */
    public function __construct(string $companyId, $environment, $folder)
    {
        $this->_logger = Zend_Registry::get('logger');
        $this->companyId = $companyId;
        $this->environment = $environment;
        $this->folder = $folder;
        $this->iprotoApiClient = new IprotoApiClient($environment);
    }

    /**
     * @param string $path
     * @param array $filter
     * @return array
     */
    public function getStores(string $path, array $filter): array
    {
        try {
            $c_id = (int)$this->companyId;
            if (empty($c_id)) {
                throw new Exception('company id parameter missing of empty');
            }

            if (empty($path)) {
                throw new Exception('file path to yest feed missing of empty');
            }

            $requestBody = [
                'company_id' => $c_id,
                'path' => $path,
                'filter' => $filter
            ];

            $config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/apiClient.ini', $this->environment);

            $curlOpt = [
                CURLOPT_URL => $config->config->iproto->host . '/api/yext/stores',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_HTTPHEADER => ['Authorization:Bearer ' . $this->iprotoApiClient->getIprotoApiToken()],
                CURLOPT_POSTFIELDS => json_encode($requestBody)
            ];

            $ch = curl_init();

            $x = 1;
            $maxApiAttempts = 6;
            $response['http_code'] = 500;
            while ($x < $maxApiAttempts) {
                $this->_logger->info('Yext API request attempt: ' . $x);
                sleep(pow($x, 2));
                curl_setopt_array($ch, $curlOpt);
                $response['body'] = curl_exec($ch);
                $response['http_code'] = curl_getinfo($ch)['http_code'];
                if ($response['http_code'] == 200) {
                    break;
                }

                $x++;
                $this->_logger->warn("HTTP response code: " .$response['http_code']);
                $this->_logger->warn($response['body']);

                if ($response['http_code'] == 401 && $response['body'] == '{"message":"Authentication Required"}') {
                    $this->_logger->info('trying different authentication method');
                    $curlOpt[CURLOPT_HTTPHEADER] = ['X-AUTH-TOKEN:' . $config->config->iproto->secret_key];
                }

                $this->_logger->warn('error during Yext API request, retry ' . $x . '/' . $maxApiAttempts);
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

    /**
     * Mapping the content of Yext's store csv file to marktjagd api stores
     * @param string $yextFeed
     * @return Marktjagd_Collection_Api_Store
     * @throws Exception
     */
    public function mapYextStoreData(string $yextFeed): array
    {
        $separator = "\r\n";
        $line = strtok($yextFeed, $separator);

        $dataSource = array();
        while ($line !== false) {
            $store = json_decode($line, true);
            array_push($dataSource, $store);
            $line = strtok( $separator );
        }

        if (count($dataSource) == 0) {
            throw new Exception('ERROR: No stores to map');
        }

        $this->_logger->info('Mapping yext stores');
        $cStores = new Marktjagd_Collection_Api_Store();
        $yextIds = [];
        foreach ($dataSource as $store) {

            if (array_key_exists('closed', $store)) {
                if ($store['closed'] == true) {
                    $this->_logger->info($store['yextId'] . ': store closed!');
                    continue;
                }
            } elseif (array_key_exists('status', $store)) {
                if ($store['status'] == 'INACTIVE') {
                    $this->_logger->info($store['yextId'] . ': store inactive!');
                    continue;
                }
            } else {
                $this->_logger->info($store['yextId'] . ': Array keys closed/status did not exist');
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $ret = $this->mapAddress($store, $eStore);
            if ($ret == false) {
                $this->_logger->warn('store: ' . $store['yextId'] . ' skipped!');
                continue;
            }

            $this->mapPhone($store, $eStore);
            $this->mapEmail($store, $eStore);
            $this->mapUrl($store, $eStore);
            $this->mapDescription($store, $eStore);
            $this->mapStoreHours($store, $eStore);
            $this->mapPaymentOptions($store, $eStore);
            $this->mapImages($store, $eStore);
            $this->mapServices($store, $eStore);
            $this->mapTitle($store, $eStore);
            $this->mapStoreNumber($store, $eStore);
            $yextIds[$store['entityId']] = $store['yextId'];

            if (!$cStores->addElement($eStore)) {
                $this->_logger->err($store['yextId'] . ': was not able to add store to collection');
            }
        }
        return ['cStores' => $cStores, 'yextIds' => $yextIds];
    }

    /**
     * @param $cStores
     * @param Crawler_Generic_Response $crawlerResponse
     * @param int $idCrawlerConfig
     * @return Crawler_Generic_Response
     */
    public function importYextStoresAndCreateReceipt($yextIds, $cStores, Crawler_Generic_Response $crawlerResponse, int $idCrawlerConfig, $country): Crawler_Generic_Response
    {
        $sApi = new Marktjagd_Service_Output_MarktjagdApi();
        $sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
        $cCrawlerConfigs = $sCrawlerConfig->findById($idCrawlerConfig);

        $crawler_Generic_Response = $sApi->import($cCrawlerConfigs, $crawlerResponse);

        $importId = $crawler_Generic_Response->getImportId();
        if ($crawler_Generic_Response->getLoggingCode() == Crawler_Generic_Response::IMPORT_FAILURE_ADD) {
            $this->_logger->err('Error during import: ' . $importId);
            return $crawlerResponse;
        } else {
            $import = $this->waitForImportToFinish($importId);
            if ($import == null) {
                $crawlerResponse->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE);
                return $crawlerResponse;
            } else {
                return $crawlerResponse;
//                return $this->createReceiptAndReturnResponse($crawlerResponse, $importId, $import, $this->companyId, $cStores, $yextIds, $country);
            }
        }
    }

    /**
     * @param int $importId
     * @return null
     */
    private function waitForImportToFinish(int $importId)
    {
        // XXX: We have not implemented this function-call for the iProto-API, so it should either be removed or implemented.
        if (ApiServiceProvider::getDefaultApi() == ApiServiceProvider::IPROTO) throw new \BadMethodCallException('not implemented for iProto-API');

        $i = 0;
        do {
            $i += 1;
            if ($i >= 1200) { $this->_logger->err('Error during import - timeout for import: ' . $importId); return null; } // Timeout after 200 minutes
            $import = Resource\Import\ImportResource::find($importId, ['with_errors' => 1, 'with_warnings' => 1, 'with_infos' => 1]);
            $importState = (string) $import->status;
            sleep(10);
        } while (!in_array($importState, ['skipped', 'done', 'error']));
        return $import;
    }

    /**
     * @param Crawler_Generic_Response $response
     * @param $importId
     * @param $import
     * @param $companyId
     * @param $cStores
     * @return Crawler_Generic_Response
     */
    private function createReceiptAndReturnResponse(Crawler_Generic_Response $response, $importId, $import, $companyId, $cStores, $yextIds, $country): Crawler_Generic_Response
    {
        $importState = (string) $import->status;
        if ($importState == 'done') {
            $ret = $this->createReceipt($response, $import, $companyId, $cStores, $yextIds, $country);
            if ($ret == null) {
                $this->_logger->err('Error during receipt creation for import: ' . $importId);
                $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE);
                return $response;
            } else {
                $this->_logger->info('Receipt created successfully for import: ' . $importId);
                $response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
                return $response;
            }
        } elseif ($importState == 'error') {
            $this->_logger->err('Error during import: ' . $importId);
            $this->createReceiptWithErrorMessages($response, $import, $companyId, $cStores, $yextIds);
            $this->createReceipt($response, $import, $companyId, $cStores, $yextIds, $country);
            $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE);
            return $response;
        } elseif ($importState == 'skipped') {
            $this->_logger->info('Import: ' . $importId . ' was skipped!');
            $response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            $response->setFileName("");
            return $response;
        } else {
            $this->_logger->err('UNKNOWN state: ' . $importState . ' for import: ' . $importId);
            $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE);
            return $response;
        }
    }

    /**
     * @param Crawler_Generic_Response $response
     * @param $import
     * @param $companyId
     * @param $cStores
     * @return Crawler_Generic_Response|null
     */
    private function createReceipt(Crawler_Generic_Response $response, $import, $companyId, $cStores, $yextIds, $country): ?Crawler_Generic_Response
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $apiStores = $sApi->findAllStoresForCompany($companyId, 100000);

        if (gettype($apiStores) == 'bool') {
            $this->_logger->err('No stores found over API!');
            return null;
        }

        $receipt = '';
        foreach ($cStores->getElements() as $cStore) {
            $receipt = $receipt . $this->createReceiptEntry($import, $cStore, $apiStores, $companyId, $yextIds, $country) . PHP_EOL;
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

    private function createReceiptWithErrorMessages(Crawler_Generic_Response $response, $import, $companyId, $cStores, $yextIds): ?Crawler_Generic_Response
    {
        $receipt = '';
        $keys = array_keys($cStores->getElements());
        foreach ($import->import_errors as $importError) {
            $storeNumber = $keys[$importError->record];
            $receipt = $receipt . $this->createErrorReceiptEntry($import, $importError, $storeNumber, $yextIds[$storeNumber]) . PHP_EOL;
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

    private function createErrorReceiptEntry($import, $importError, $storeNumber, $yextId)
    {
        $receiptEntry = [];

        $receiptEntry['yextId'] = $yextId;
        $receiptEntry['partnerId'] = null;
        $receiptEntry['status'] = 'error';
        $receiptEntry['details'] = $importError->message;
        $receiptEntry['url'] = null;
        return json_encode($receiptEntry);
    }

    /**
     * @param $import
     * @param $cStore
     * @param $apiStores
     * @param $companyId
     * @return false|string
     */
    private function createReceiptEntry($import, $cStore, &$apiStores, $companyId, $yextIds, $country)
    {
        $receiptEntry = [];

        $receiptEntry['partnerId'] = null;
        $receiptEntry['details'] = '';
        $receiptEntry['yextId'] = $yextIds[$cStore->getStoreNumber()];

        $storeId = null;
        $storeNumber = $cStore->getStoreNumber();
        $apiStores = array_filter($apiStores, function($v, $k) use($storeNumber, &$storeId) {
            if ($v['number'] == $storeNumber) {
                $storeId = $k;
                return false;
            } else {
                return true;
            }
        }, ARRAY_FILTER_USE_BOTH);

        if ($storeId == null) {
            $this->_logger->err('No stores found over Offerista API for storeNumber / yextId:' . $receiptEntry['storeNumber'] . ' / ' . $receiptEntry['yextId']);
            $receiptEntry['status'] = 'error';
            $receiptEntry['details'] = 'Store was not imported. No stores found over Offerista API';
        } else {
            $receiptEntry['partnerId'] = $storeId;
            $receiptEntry['status'] = 'success';
            if ($country == 'DE'){
                $receiptEntry['url'] = $this->createMarktjagdStoreUrl($cStore, $storeId, $companyId);
            } elseif ($country == 'AT'){
                $receiptEntry['url'] = $this->createWogibtswasStoreUrl($cStore, $storeId, $companyId);
            }
            if (!array_key_exists('url', $receiptEntry)) {
                $receiptEntry['status'] = 'error';
                $receiptEntry['details'] = 'Error during URL creation, some necessary urls are missing.';
            }
        }

        return json_encode($receiptEntry);
    }

    /**
     * @param $cStore
     * @param $storeId
     * @param $companyId
     * @return string|null
     */
    private function createMarktjagdStoreUrl($cStore, $storeId, $companyId): ?string
    {
        if ($this->environment == 'production') {
            // https://www.marktjagd.de/berlin/filiale/lidl-charlottenstrasse:198272
            $url = 'https://www.marktjagd.de/f/';
        } else {
            // https://marktjagd-de.legacy-portal-elb.frontend-stage.offerista.com/berlin/filiale/scoops-ahoy-eiscreme-rykestrasse:997828
            $url = 'https://marktjagd-de.legacy-portal-elb.frontend-stage.offerista.com/';
        }

        return $this->createStoreUrl($cStore, $storeId, $url);
    }

    /**
     * @param $cStore
     * @param $storeId
     * @param $companyId
     * @return string|null
     */
    private function createBarcooStoreUrl($cStore, $storeId, $companyId): ?string
    {
        if ($this->environment == 'production') {
            // https://www.wogibtswas.at/f/1155177-billa-zwettlerstrasse-10-3902-vitis
            $url = 'https://www.barcoo.de/f/';
        } else {
            // https://barcoo-de.portal-elb.frontend-stage.offerista.com/f/997828-scoops-ahoy-eiscreme-rykestrasse-40-10405-berlin
            $url = 'https://barcoo-de.portal-elb.frontend-stage.offerista.com/f/';
        }

        return $this->createStoreUrl($cStore, $storeId, $url);
    }

    /**
     * @param $cStore
     * @param $storeId
     * @param $companyId
     * @return string|null
     */
    private function createWogibtswasStoreUrl($cStore, $storeId, $companyId): ?string
    {
        if ($this->environment == 'production') {
            // https://www.barcoo.de/f/609178-e-center-provianthofstrasse-3-01099-dresden
            $url = 'https://www.wogibtswas.at/f/';
        } else {
            // https://wogibtswas-at.portal-elb.frontend-dev.offerista.com/f/951734-hofer-atterseestrasse-95-4850-timelkam
            $url = 'https://wogibtswas-at.portal-elb.frontend-dev.offerista.com//f/';
        }

        return $this->createStoreUrl($cStore, $storeId, $url);
    }

    /**
     * @param $cStore
     * @param $storeId
     * @param $url
     * @return string|null
     */
    private function createStoreUrl($cStore, $storeId, $url): ?string
    {
        $storeUrl = $storeId . '-' . $cStore->getTitle() . '-' . $cStore->getStreet(). '-' . $cStore->getStreetNumber() . '-' . $cStore->getZipcode() . '-' . $cStore->getCity();
        $sluggified = $this->slugify($storeUrl);

        if ($sluggified == null) {
            return null;
        }

        return $url . $sluggified;
    }

    /**
     * @param string $string
     * @return false|string|null
     */
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
            $storeNumberInReceipt = $store['yextId'];
            if (!in_array($storeNumberInReceipt, $storeNumbers)) {
                $oldBodyFiltered .= $line . PHP_EOL;
            }
            $line = strtok( $separator );
        }
        return $oldBodyFiltered . PHP_EOL . $receipt;
    }



    public function uploadReceipt(string $receipt, $companyId, $cStores)
    {
        $this->_logger->info('Uploading receipt!');

        try {
            $provider = CredentialProvider::instanceProfile();
            $memoizedProvider = CredentialProvider::memoize($provider);

            $stsClient = new Aws\Sts\StsClient([
                'region' => 'eu-west-1',
                'version' => '2011-06-15',
                'credentials' => $memoizedProvider
            ]);

            $result = $stsClient->AssumeRole([
                'RoleArn' => 'arn:aws:iam::385750204895:role/yext-s3-access.di-prod',
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

            $receiptAlreadyExists = $s3Client->doesObjectExist('yext-offerista-feed', $this->folder . '/receipt_listings_' . date('Y-m-d') . '.json');

            if ($receiptAlreadyExists == false) {
                $result = $s3Client->putObject([
                    'Bucket' => 'yext-offerista-feed',
                    'Key' => $this->folder . '/receipt_listings_' . date('Y-m-d') . '.json',
                    'Body' => $receipt,
                    'ACL' => 'public-read'
                ]);
                $this->_logger->info('Receipt s3 key:' . $result['ObjectURL']);
            } else {
                $this->_logger->info('yext receipt already existed, querying body for override.');
                $object = $s3Client->getObject(array(
                    'Bucket' => 'yext-offerista-feed',
                    'Key' => $this->folder . '/receipt_listings_' . date('Y-m-d') . '.json'
                ));

                $body = $object['Body']->getContents();

                $newBody = $this->createNewAndOverrideExistingReceiptEntries($body, $receipt, $cStores);

                $result = $s3Client->putObject([
                    'Bucket' => 'yext-offerista-feed',
                    'Key' => $this->folder . '/receipt_listings_' . date('Y-m-d') . '.json',
                    'Body' => $newBody . PHP_EOL,
                    'ACL' => 'public-read'
                ]);
                $this->_logger->info('Receipt s3 key:' . $result['ObjectURL']);
            }

            $object = $s3Client->getObject(array(
                'Bucket' => 'yext-offerista-feed',
                'Key' => $this->folder . '/receipt_listings_' . date('Y-m-d') . '.json'
            ));

        } catch (S3Exception $e) {
            echo $e->getMessage();
            return (string) $e->getMessage();
        }

        return true;
    }

    public function mapAddress($store, Marktjagd_Entity_Api_Store &$eStore): bool
    {
        if (!array_key_exists('address', $store)) {
            $this->_logger->warn($store['yextId'] . ': Property address does not exist!');
            return false;
        }

        if (!array_key_exists('address', $store)) {
            $this->_logger->warn($store['yextId'] . ': Property address does not exist!');
            return false;
        }

        if (!array_key_exists('address', $store)) {
            $this->_logger->warn($store['yextId'] . ': Property city does not exist!');
            return false;
        }

        if (!array_key_exists('address', $store)) {
            $this->_logger->warn($store['yextId'] . ': Property postalCode does not exist!');
            return false;
        }

        $eStore->setStreetAndStreetNumber(normalizer_normalize($store['address']['address'], Normalizer::NFC));
        $eStore->setCity(normalizer_normalize($store['address']['city'], Normalizer::NFC));
        $eStore->setZipcode($store['address']['postalCode']);

        if (array_key_exists('geoData', $store)) {
            if (array_key_exists('geoData', $store )){
                $eStore->setLatitude($store['geoData']['displayLatitude']);
            }
            if (array_key_exists('geoData', $store )) {
                $eStore->setLongitude($store['geoData']['displayLongitude']);
            }
        }

        return true;
    }

    public function mapPhone($store, Marktjagd_Entity_Api_Store &$eStore): void
    {
        if (array_key_exists('phones', $store)) {
            foreach ($store['phones'] as $number) {
                if (array_key_exists('type', $number)) {
                    if ($number['type'] == 'MAIN') {
                        $eStore->setPhoneNormalized($number['number']['number']);
                    } elseif ($number['type'] == 'FAX') {
                        $eStore->setFaxNormalized($number['number']['number']);
                    }
                }
            }
        } else {
            $this->_logger->info($store['yextId'] . ': no phone numbers exist');
        }
    }

    public function mapEmail($store, Marktjagd_Entity_Api_Store $eStore): void
    {
        if (array_key_exists('emails', $store)) {
            $emailTmp = array_pop($store['emails']);
            if (gettype($emailTmp) == 'array' and array_key_exists('address', $emailTmp) and gettype($emailTmp['address'] == 'string')) {
                $eStore->setEmail($emailTmp['address']);
            } else {
                $this->_logger->info($store['yextId'] . ': emails do not exist!');
            }
        } else {
            $this->_logger->info($store['yextId'] . ': emails do not exist!');
        }
    }

    public function mapUrl($store, Marktjagd_Entity_Api_Store $eStore): void
    {
        if (array_key_exists('urls', $store)) {
            foreach ($store['urls'] as $url) {
                if (array_key_exists('type', $url)) {
                    if ($url['type'] == 'WEBSITE') {
                        $eStore->setWebsite(preg_replace('#http://#', 'https://', $url['url']));
                        break;
                    }
                }
            }
        } else {
            $this->_logger->info($store['yextId'] . ': urls did not exist!');
        }
    }

    public function mapDescription($store, Marktjagd_Entity_Api_Store $eStore): void
    {
        if (array_key_exists('description', $store)) {
            $eStore->setText($store['description']);
        } else {
            $this->_logger->info($store['yextId'] . ': description did not exist!');
        }
    }

    public function mapStoreHours($store, Marktjagd_Entity_Api_Store $eStore): void
    {
        if (array_key_exists('hours', $store)) {
            $storeHours = '';
            foreach ($store['hours'] as $day) {
                foreach ($day['intervals'] as $interval) {
                    $storeHours = $storeHours . $day['day'] . ': ' . $interval['start'] . '-' . $interval['end'] .', ';
                }
            }
            $eStore->setStoreHoursNormalized($storeHours);
        } elseif (array_key_exists('reopenDate', $store)) {
            if ($store['reopenDate'] != '') {
                $eStore->setText('Wiedereröffnung: '. $store['reopenDate'] . '\n' . $eStore->getText());
            }
        } else {
            $this->_logger->info($store['yextId'] . ': hours did not exist!' );
        }

        if (array_key_exists('hoursText', $store)) {
            if (array_key_exists('hoursText', $store)) {
                if (array_key_exists('additional', $store['hoursText'])){
                    if ($store['hoursText']['additional'] != 'Oeffnungszeiten') {
                        $eStore->setStoreHoursNotes($store['hoursText']['additional']);
                    }
                }
            }
        }
    }

    public function mapPaymentOptions($store, Marktjagd_Entity_Api_Store $eStore): void
    {
        if (array_key_exists('paymentOptions', $store)) {
            if (gettype($store['paymentOptions'] == 'array')) {
                $eStore->setPayment(implode(', ', $store['paymentOptions']));
            }
        } else {
            $this->_logger->info($store['yextId'] . ': paymentOptions did not exist!' );
        }
    }

    public function mapImages($store, Marktjagd_Entity_Api_Store $eStore): void
    {
        if (array_key_exists('images', $store)) {
            foreach ($store['images'] as $image){
                if (array_key_exists('type', $image)) {
                    if ($image['type'] == 'LOGO' and empty($eStore->getLogo())) {
                        $eStore->setLogo($image['url']);
                    } elseif ($image['type'] == 'IMAGE' and empty($eStore->getImage())) {
                        $eStore->setImage($image['url']);
                    } elseif ($image['type'] == 'GALLERY' and empty($eStore->getImage())) {
                        $eStore->setImage($image['url']);
                    }
                }
            }
        } else {
            $this->_logger->debug($store['yextId']. ': images did not exist!');
        }
    }

    public function mapServices($store, Marktjagd_Entity_Api_Store $eStore): void
    {
        if (array_key_exists('services', $store)) {
            $eStore->setService(implode(', ', $store['services']));
        } else {
            $this->_logger->debug($store['yextId']. ': services did not exist!');
        }
    }

    public function mapTitle($store, Marktjagd_Entity_Api_Store $eStore): void
    {
        if (array_key_exists('name', $store)) {
            $eStore->setTitle($store['name']);
        } else {
            $this->_logger->info($store['yextId']. ': name did not exist!');
        }
    }

    public function mapStoreNumber($store, Marktjagd_Entity_Api_Store $eStore): void
    {
        if (array_key_exists('entityId', $store)) {
            $eStore->setStoreNumber($store['entityId']);
        } else {
            $this->_logger->info($store['yextId'] . ': storenumber did not exist!');
        }
    }
}


