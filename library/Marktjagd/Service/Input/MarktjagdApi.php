<?php

use Marktjagd\ApiClient\Request\Request;
use Marktjagd\ApiClient\Resource;
use Marktjagd\ApiClient\Resource\ResourceFactory;
use Marktjagd\Service\IprotoApi\ApiServiceProvider;

ResourceFactory::setClasses(array());

/**
 * Service zum Abfragen von Daten aus der Marktjagd-API
 */
class Marktjagd_Service_Input_MarktjagdApi
{

    /**
     * Findet alle Stores für eine Company
     *
     * @param $companyId
     * @param int $count @deprecated
     * @param bool $visibleOnly
     * @return Marktjagd_Collection_Api_Store
     */
    public function findStoresByCompany($companyId, $count = 500, $visibleOnly = true)
    {
        return ApiServiceProvider::getApiService()->findStoresByCompany($companyId, $visibleOnly);
    }

    public function createStore($store)
    {
        return ApiServiceProvider::getApiService()->createStore($store);
    }

    public function findArticleById($companyId, $id)
    {
        return ApiServiceProvider::getApiService()->findArticleById($companyId, $id);
    }

    public function findCompanyByName($ownerId, $searchString)
    {
        return ApiServiceProvider::getApiService()->findCompanyByName($ownerId, $searchString);
    }

    /**
     * Ermittelt alle Distributionen für eine Company
     *
     * @param int $companyId
     * @param int $count @deprecated
     * @return Marktjagd_Collection_Api_Distribution
     * @throws Exception
     */
    function findDistributionsByCompany($companyId, $count = 500)
    {
        return ApiServiceProvider::getApiService()->findDistributionsByCompany($companyId);
    }

    /**
     * Fragt bei der Api Standorte für ein Unternehmen anhand der Distribution ab
     * Wird $excludeDistribution=true gesetzt, werden alle Standorte, außer die der übergebenen Distribution ermittelt
     *
     * @param int $companyId
     * @param string $distribution
     * @param bool $excludeDistribution
     * @param int $count @deprecated
     * @param bool $visibleOnly
     * @return Marktjagd_Collection_Api_Store|bool
     */
    public function findStoresByDistribution($companyId, $distribution, $excludeDistribution = false, $count = 500, $visibleOnly = true)
    {
        return ApiServiceProvider::getApiService()->findStoresByDistribution($companyId, $distribution, $excludeDistribution, $visibleOnly);
    }

    /**
     * Prüft, ob das Verhalten (keep, archive, remove) zum übergebenen Typ (articles, stores, brochures) passt
     *
     * @param string $type
     * @param string $behaviour
     * @return bool
     */
    public function isValidBehaviour($type, $behaviour)
    {
        $isValid = false;
        $aTypeBehaviour = array(
            'articles' => array(
                'keep',
                'archive',
                'remove'
            ),
            'brochures' => array(
                'keep',
                'archive',
                'remove'
            ),
            'stores' => array(
                'keep',
                'remove'
            ),
        );

        if ($behaviour == 'auto') {
            $isValid = true;
        }

        if (!array_key_exists($type, $aTypeBehaviour)) {
            return false;
        }

        if (in_array($behaviour, $aTypeBehaviour[$type])) {
            $isValid = true;
        }

        return $isValid;
    }

    /**
     * Ermittelt alle Brochure-IDs aktiver Prospekte für ein Unternehmen
     *
     * @param int $companyId Unternehmens-ID
     * @param int $count @deprecated
     * @return array|bool
     */
    public function findActiveBrochuresByCompany($companyId, $count = 500)
    {
        return ApiServiceProvider::getApiService()->findActiveBrochuresByCompany($companyId);
    }

    public function findActiveBrochuresByCompanyWithDiscover($companyId)
    {
        // XXX: We have not implemented this function-call for the iProto-API, so it should either be removed or implemented.
        // Only invoked in: `./tools/di-api-client/DiscoverMigration.php`
        if (ApiServiceProvider::getDefaultApi() == ApiServiceProvider::IPROTO) throw new \BadMethodCallException('not implemented for iProto-API');

        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        /* @var $apiClient Marktjagd_Entity_MarktjagdApi */
        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $retArr = array();
        $brochures = \Marktjagd\ApiClient\Resource\Brochure\BrochureResource::findAll(
            array(
                'company_id' => $companyId,
                'time_constraint' => array('current', 'upcoming', 'future'),
                'sort' => 'modified',
                'order' => 'desc',
                'with_layout' => 1
            )
        );

        if (Request::getInstance()->hasErrors()) {
            $logger->log(
                'Error while findActiveBrochuresByCompany: company '
                . $companyId . ' - '
                . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
            );
            return false;
        }

        if (empty($brochures)) {
            return false;
        }

        foreach ($brochures as $brochure) {
            $aBrochure = $brochure->toArray();
            if (empty($aBrochure['layout'])) {
                continue;
            } else {
                $retArr[$aBrochure['id']]['brochureNumber'] = $aBrochure['number'];
                $retArr[$aBrochure['id']]['title'] = $aBrochure['title'];
                $retArr[$aBrochure['id']]['type'] = $aBrochure['type'];
                $retArr[$aBrochure['id']]['type_id'] = $aBrochure['type_id'];
                $retArr[$aBrochure['id']]['validFrom'] = $aBrochure['datetime_from'];
                $retArr[$aBrochure['id']]['validTo'] = $aBrochure['datetime_to'];
                $retArr[$aBrochure['id']]['visibleFrom'] = $aBrochure['datetime_visible_from'];
                $retArr[$aBrochure['id']]['visibleTo'] = $aBrochure['datetime_visible_to'];
                $retArr[$aBrochure['id']]['lastModified'] = $aBrochure['datetime_modified'];
                $retArr[$aBrochure['id']]['created'] = $aBrochure['datetime_created'];
                $retArr[$aBrochure['id']]['layout'] = $aBrochure['layout'];
            }
        }

        return $retArr;
    }

    /**
     * Findet Standorte eines Unternehmens mit aktiven Prospekten und ordnet diese den Prospekten zu
     *
     * @param string $brochureId Prospekt-ID
     * @param string $companyId Unternehmens-ID
     * @param int $count @deprecated
     * @return array|bool Brochure-Array mit den entsprechenden Store-Infos
     */
    public function findStoresWithActiveBrochures($brochureId, $companyId, $count = 500)
    {
        return ApiServiceProvider::getApiService()->findStoresWithActiveBrochures($brochureId, $companyId);
    }

    /**
     * ermittelt, ob eine aktive Brochure im System hinterlegt ist
     *
     * @param $companyId
     * @param string $timeToExpirationInDays
     * @return bool
     */
    public function isActiveBrochureAvailableByCompanyId($companyId, $timeToExpirationInDays = '2')
    {
        foreach ($this->findActiveBrochuresByCompany($companyId) as $singleBrochure) {
            if (is_array($singleBrochure) && (strtotime("+ $timeToExpirationInDays days") < strtotime($singleBrochure['validTo']))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ermittelt alle Artikel-IDs aktiver Artikel für ein Unternehmen
     *
     * @param int $companyId Unternehmens-ID
     * @param int $count @deprecated
     * @return array|bool
     * @throws Zend_Log_Exception
     * @throws Zend_Exception
     */
    public function findActiveArticlesByCompany($companyId, $count = 500)
    {
        return ApiServiceProvider::getApiService()->findActiveArticlesByCompany($companyId);
    }

    /**
     * Findet alle Stores eines Unternehmens
     *
     * @param string $companyId Unternehmens-ID
     * @param int $count @deprecated
     * @return boolean|array $retArr Store-Array
     */
    public function findAllStoresForCompany($companyId, $count = 500, $status = 'visible')
    {
        return ApiServiceProvider::getApiService()->findAllStoresForCompany($companyId, $status);
    }

    /**
     * Findet den letzten Standort-/Prospekt-/Produkt-Import eines Unternehmens
     *
     * @param int $companyId Unternehmens-ID
     * @param string $type zu prüfender Typ [store,article,brochure]
     * @param string $status Status des Imports
     * @param int $count Anzahl der zu prüfenden Importe
     * @return bool|string Startzeitpunkt des letzten spezifizierten Imports
     */
    public function findLastImport($companyId, $type, $status = 'done', $count = 1)
    {
        return ApiServiceProvider::getApiService()->findLastImport($companyId, $type, $status);
    }

    /**
     * Updatet Datenbank bezüglich Companies und Tarifkategorien
     */
    public function updateCrawler()
    {
        // XXX: We have not implemented this function-call for the iProto-API, so it should either be removed or implemented.
        // Only invoked in: `scripts/updateCrawler.php`
//        if (ApiServiceProvider::getDefaultApi() == ApiServiceProvider::IPROTO) throw new \BadMethodCallException('not implemented for iProto-API');

        echo "crawler update started.\n";
        $sDbCompany = new Marktjagd_Database_Service_Company();
        $sDbQualityCheckErrorsInfos = new Marktjagd_Database_Service_QualityCheckCompanyInfos();

        $companies = $this->_getAllCompanies();
        $aDbCompanies = $sDbCompany->findAll();

        foreach ($companies as $idCompany => $singleCompany) {
            $eCompany = new Marktjagd_Database_Entity_Company();
            $eCompany->setIdCompany($idCompany)
                ->setName($singleCompany['title'])
                ->setProductCategory($singleCompany['product_category'])
                ->setIdPartner($singleCompany['partner_id'])
                ->setStatus(preg_match('#visible#', $singleCompany['status']) ? 'active' : 'inactive');
            $eCompany->save();

            if (!$sDbQualityCheckErrorsInfos->findByCompanyId($idCompany)->getIdCompany()) {
                $eCompanyQualityCheck = new Marktjagd_Database_Entity_QualityCheckCompanyInfos();
                $eCompanyQualityCheck->setIdCompany($idCompany)
                    ->setStores(1)
                    ->setBrochures(0)
                    ->setProducts(0)
                    ->setLimitStores(0.75)
                    ->setLimitBrochures(0)
                    ->setLimitProducts(0)
                    ->setFreshnessStores(0)
                    ->setFreshnessBrochures(0)
                    ->setFreshnessProducts(0)
                    ->setFutureBrochures(0)
                    ->setFutureProducts(0);

                $eCompanyQualityCheck->save();
            }
        }

        foreach ($aDbCompanies as $singleDbCompany) {
            if (!array_key_exists($singleDbCompany->getIdCompany(), $companies) && !preg_match('#removed#', $singleDbCompany->getStatus()) && !preg_match('#(UIM)#', $singleDbCompany->getName())) {
                $eCompany = new Marktjagd_Database_Entity_Company();
                $eCompany->setIdCompany($singleDbCompany->getIdCompany())
                    ->setName($singleDbCompany->getName())
                    ->setProductCategory($singleDbCompany->getProductCategory())
                    ->setStatus('removed');

                $eCompany->save();
            }
        }

        echo "update successful.\n";
    }

    /**
     * Findet alle Unternehmen inklusive Tarifkategorie
     *
     * @return array Unternehmens-Array inklusive Tarifkategorie
     */
    protected function _getAllCompanies()
    {
        // XXX: We have not implemented this function-call for the iProto-API, so it should either be removed or implemented.
        // Only invoked in: `updateCrawler`
//        if (ApiServiceProvider::getDefaultApi() == ApiServiceProvider::IPROTO) throw new \BadMethodCallException('not implemented for iProto-API');

        $sPartner = new Marktjagd_Database_Service_Partner();
        $cPartner = $sPartner->findAll();

        $aCompanies = array();

        /** @var Marktjagd_Database_Entity_Partner $ePartner */
        foreach ($cPartner as $ePartner) {
            if ($ePartner->getIdPartner() == 8) {
                continue;
            }
            $apiClient = new Marktjagd_Entity_MarktjagdApi();
            $apiClient->setEnvironment($ePartner);
            $count = 500;
            $page = 1;

            do {
                $companies = Resource\Company\CompanyResource::findAll(
                    array(
                        'count' => $count,
                        'page' => $page++,
                        'with_offer_flags' => 'false'
                    ));

                $product = Resource\Product\ProductResource::findAll(
                    array(
                        'count' => $count
                    ));
                if (!is_iterable($companies) || !is_iterable($product)) {
                    continue;
                }
                foreach ($companies as $company) {
                    foreach ($product as $singleProduct) {

                        if ($singleProduct->id == $company->product_id) {
                            $aCompanies[$company->id]['title'] = $company->title;
                            $aCompanies[$company->id]['product_category'] = $singleProduct->category;
                            $aCompanies[$company->id]['status'] = $company->status;
                            if (preg_match('#TOP#', $singleProduct->title)) {
                                $aCompanies[$company->id]['product_category'] = $singleProduct->title;
                            }

                            $aCompanies[$company->id]['partner_id'] = $ePartner->getIdPartner();
                        }
                    }
                }

            } while ((is_array($companies) || $companies instanceof Countable) && count($companies) == $count);
        }

        asort($aCompanies, SORT_STRING);

        return $aCompanies;
    }

    /**
     * Findet alle aktiven Unternehmen inklusive Tarifkategorie
     *
     * @return array Unternehmens-Array inklusive Tarifkategorie
     */
    public function getAllActiveCompanies()
    {
        // XXX: We have not implemented this function-call for the iProto-API, so it should either be removed or implemented.
        // Only invoked in:
        // - "tools/filterGettingsList.php"
        // - "tools/readKaufDaList.php"
        if (ApiServiceProvider::getDefaultApi() == ApiServiceProvider::IPROTO) throw new \BadMethodCallException('not implemented for iProto-API');

        $sPartner = new Marktjagd_Database_Service_Partner();
        $cPartner = $sPartner->findAll();

        $aCompanies = array();

        /** @var Marktjagd_Database_Entity_Partner $ePartner */
        foreach ($cPartner as $ePartner) {
            $apiClient = new Marktjagd_Entity_MarktjagdApi();
            $apiClient->setEnvironment($ePartner);
            $count = 500;
            $page = 1;

            do {
                $companies = Resource\Company\CompanyResource::findAll(
                    array(
                        'count' => $count,
                        'page' => $page++,
                        'status' => Resource\Store\StoreResource::STATUS_VISIBLE,
                        'with_offer_flags' => 'false'
                    ));

                $product = Resource\Product\ProductResource::findAll(
                    array(
                        'count' => $count
                    ));

                foreach ($companies as $company) {
                    foreach ($product as $singleProduct) {
                        if (preg_match('#^(Basis)#', $singleProduct->title)) {
                            continue;
                        }
                        if ($singleProduct->id == $company->product_id) {
                            $aCompanies[$company->id]['title'] = $company->title;
                            $aCompanies[$company->id]['product_category'] = $singleProduct->category;
                            $aCompanies[$company->id]['url'] = $company->homepage;
                            if (preg_match('#top#i', $singleProduct->title)) {
                                $aCompanies[$company->id]['product_category'] = $singleProduct->title;
                            }

                            if ($singleProduct->cpc_brochure != 0) {
                                $aCompanies[$company->id]['brochure'] = TRUE;
                            }

                            if ($singleProduct->cpc_article != 0) {
                                $aCompanies[$company->id]['article'] = TRUE;
                            }

                            $aCompanies[$company->id]['logo_url'] = $company->images->image[0]->dimensions->dimension[0]->url;
                            $aCompanies[$company->id]['partner_id'] = $ePartner->getIdPartner();
                        }
                    }
                }
            } while (count($companies) == $count);
        }

        ksort($aCompanies);

        return $aCompanies;
    }

    /**
     * Findet Standorte eines Unternehmens mit aktiven Produkten
     *
     * @param string $companyId Unternehmens-ID
     * @param int $count Anzahl der Einträge pro Seite
     * @return array|bool Store-Array mit aktiven Produkten
     */
    public function findStoresWithActiveProducts($companyId, $count = 500)
    {
        // XXX: We have not implemented this function-call for the iProto-API, so it should either be removed or implemented.
        // Only invoked in: `tools/getStoresForProducts.php`
        if (ApiServiceProvider::getDefaultApi() == ApiServiceProvider::IPROTO) throw new \BadMethodCallException('not implemented for iProto-API');

        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        $retArr = false;

        /* @var $apiClient Marktjagd_Entity_MarktjagdApi */
        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $page = 1;
        do {
            $products = \Marktjagd\ApiClient\Resource\Store\StoreResource::findAll(
                array(
                    'company_id' => $companyId,
                    'has_articles' => '1',
                    'status' => 'visible',
                    'count' => $count,
                    'page' => $page++
                )
            );

            if (Request::getInstance()->hasErrors()) {
                $logger->log(
                    'Error while findActiveBrochuresByCompany for company '
                    . $companyId . ' - '
                    . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
                );
                return false;
            }

            foreach ($products as $product) {
                $aProduct = $product->toArray();
                $retArr[$aProduct['id']]['number'] = $aProduct['number'];
                $retArr[$aProduct['id']]['title'] = $aProduct['title'];
                $retArr[$aProduct['id']]['street'] = $aProduct['street'];
                $retArr[$aProduct['id']]['street_number'] = $aProduct['street_number'];
                $retArr[$aProduct['id']]['zipcode'] = $aProduct['zipcode'];
                $retArr[$aProduct['id']]['city'] = $aProduct['city'];
                $retArr[$aProduct['id']]['lng'] = $aProduct['longitude'];
                $retArr[$aProduct['id']]['lat'] = $aProduct['latitude'];
            }
        } while (count($products) == $count);

        return $retArr;
    }

    public function findStoreByStoreId($storeId, $companyId)
    {
        return ApiServiceProvider::getApiService()->findStoreByStoreId($storeId, $companyId);
    }

    public function findStoreByStoreNumber($storeNumber, $companyId = '')
    {
        return ApiServiceProvider::getApiService()->findStoreByStoreNumber($storeNumber, $companyId);
    }

    public function findStoreNumbersByPostcode($postcode, $companyId)
    {
        return ApiServiceProvider::getApiService()->findStoreNumbersByPostcode($postcode, $companyId);
    }

    /**
     * @param $companyId
     * @param int $count @deprecated
     * @param string $timeConstraint
     * @return array|bool
     */
    public function findStoresWithBrochures($companyId, int $count = 500, string $timeConstraint = 'current')
    {
        return ApiServiceProvider::getApiService()->findStoresWithBrochures($companyId, $timeConstraint);
    }

    public function findCompanyByCompanyId($companyId, $industryId = false)
    {
        return ApiServiceProvider::getApiService()->findCompanyByCompanyId($companyId, $industryId);
    }

    public function findManufacturerTagByArticleId($companyId, $articleId)
    {
        return ApiServiceProvider::getApiService()->findManufacturerTagByArticleId($companyId, $articleId);
    }

    public function findArticleByArticleNumber($companyId, $articleNumber)
    {
        return ApiServiceProvider::getApiService()->findArticleByArticleNumber($companyId, $articleNumber);
    }

    /***
     * @return array|bool
     */
    public function findUpcomingArticleByNumber(int $companyId, string $articleNumber)
    {
        return ApiServiceProvider::getApiService()->findUpcomingArticleByNumber($companyId, $articleNumber);
    }

    /**
     * Get article collection from core for given company id
     * @param $companyId
     * @param int $count @deprecated
     * @return bool|Marktjagd_Collection_Api_Article
     * @throws Zend_Exception
     */
    public function getActiveArticleCollection($companyId, $count = 500)
    {
        return ApiServiceProvider::getApiService()->getActiveArticleCollection($companyId);
    }

    public function findImportById(int $companyId, int $importId)
    {
        return ApiServiceProvider::getApiService()->findImportById($companyId, $importId);
    }

    public function findBrochureByBrochureNumberAndCompany(string $brochureNumber, int $companyId)
    {
        return ApiServiceProvider::getApiService()->findBrochureByBrochureNumberAndCompany($brochureNumber, $companyId);
    }

    public function createBrochure(array $brochure)
    {
        return ApiServiceProvider::getApiService()->createBrochure($brochure);
    }

    public function createBrochureImagesBatch(array $imageUrls)
    {
        return ApiServiceProvider::getApiService()->createBrochureImagesBatch($imageUrls);
    }

    public function getBrochureImagesByBatchId(string $batchId, int $maxBatchWaitTime = 1024)
    {
        return ApiServiceProvider::getApiService()->getBrochureImagesByBatchId($batchId, $maxBatchWaitTime);
    }

    public function createSalesRegionFromStoreNumbers(int $integrationId, array $storeNumbers)
    {
        return ApiServiceProvider::getApiService()->createSalesRegionFromStoreNumbers($integrationId, $storeNumbers);
    }

    public function createSalesRegionForTheWholeCountry(int $integrationId, string $countryCode)
    {
        return ApiServiceProvider::getApiService()->createSalesRegionForTheWholeCountry($integrationId, $countryCode);
    }

    public function createSalesRegion(array $iprotoCreateSalesRegionRequest)
    {
        return ApiServiceProvider::getApiService()->createSalesRegion($iprotoCreateSalesRegionRequest);
    }

    public function createIntegration(array $integrationData)
    {
        return ApiServiceProvider::getApiService()->createIntegration($integrationData);
    }
}
