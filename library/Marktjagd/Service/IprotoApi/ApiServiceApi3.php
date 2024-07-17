<?php

namespace Marktjagd\Service\IprotoApi;

use Crawler_Generic_Response;
use Marktjagd\ApiClient\Request\Request;
use Marktjagd\ApiClient\Resource\Article\ArticleResource;
use Marktjagd\ApiClient\Resource\Brochure\BrochureResource;
use Marktjagd\ApiClient\Resource\Company\CompanyResource;
use Marktjagd\ApiClient\Resource\Distribution\DistributionResource;
use Marktjagd\ApiClient\Resource\Import\ImportResource;
use Marktjagd\ApiClient\Resource\ResourceFactory;
use Marktjagd\ApiClient\Resource\Store\StoreResource;
use Marktjagd\ApiClient\Resource\Tag\TagResource;
use Marktjagd_Collection_Api_Article;
use Marktjagd_Collection_Api_Distribution;
use Marktjagd_Collection_Api_Store;
use Marktjagd_Database_Entity_CrawlerConfig;
use Marktjagd_Database_Service_Partner;
use Marktjagd_Entity_Api_Article;
use Marktjagd_Entity_Api_Distribution;
use Marktjagd_Entity_Api_Store;
use Marktjagd_Entity_MarktjagdApi;
use Marktjagd_Service_Output_File;
use Zend_Log;
use Zend_Registry;
use Zend_Config_Ini;

ResourceFactory::setClasses(array());

/**
 * All input and output API-functions for the APIv3.
 */
class ApiServiceApi3 implements ApiServiceInterface
{
    private int $count = 500;

    public function findStoresByCompany(int $companyId, bool $visibleOnly = true): Marktjagd_Collection_Api_Store
    {
        // Alle Vertriebsbereiche ermitteln
        $cDistributions = $this->findDistributionsByCompany($companyId);
        $aDistributionMap = array();

        foreach ($cDistributions->getElements() as $eDistribution) {
            // Für jeden Vertriebsbereich seperat die Standorte ermitteln und Zugehörigkeit merken
            /* @var $eDistribution Marktjagd_Entity_Api_Distribution */
            $cDistributionStores = $this->findStoresByDistribution($companyId, $eDistribution->getTitle(), false, $visibleOnly);
            foreach ($cDistributionStores->getElements() as $eDistributionStore) {
                /* @var $eDistributionStore Marktjagd_Entity_Api_Store */
                $aDistributionMap[$eDistributionStore->getId()][] = $eDistribution->getTitle();
            }
        }

        // Nochmal alle Standorte ermitteln und Vertriebsbereiche entsprechend zuordnen
        $cStoresWithDistribution = new Marktjagd_Collection_Api_Store();
        $cStoresWithoutDistribution = $this->findStoresByDistribution($companyId, null, false, $visibleOnly);
        foreach ($cStoresWithoutDistribution->getElements() as $eStoreWithoutDistribution) {
            /* @var $eStoreWithoutDistribution Marktjagd_Entity_Api_Store */
            if (array_key_exists($eStoreWithoutDistribution->getId(), $aDistributionMap)) {
                $eStoreWithoutDistribution->setDistribution(implode(',', $aDistributionMap[$eStoreWithoutDistribution->getId()]));
            }
            $cStoresWithDistribution->addElement($eStoreWithoutDistribution);
        }

        return $cStoresWithDistribution;
    }

    function findDistributionsByCompany(int $companyId): Marktjagd_Collection_Api_Distribution
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);
        $cDistribution = new Marktjagd_Collection_Api_Distribution();

        $page = 1;

        do {
            $distributions = DistributionResource::findAll(
                array(
                    'company_id' => $companyId,
                    'count' => $this->count,
                    'page' => $page++,
                    'status' => DistributionResource::STATUS_VISIBLE,
                    'is_static' => false
                )
            );

            if (Request::getInstance()->hasErrors()) {
                $logger->log(
                    'Error while getDistributions: company '
                    . $companyId . ' - '
                    . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
                );
                throw new \Exception(print_r(Request::getInstance()->getErrors(), true));
            }

            foreach ($distributions as $distribution) {
                $aDistribution = $distribution->toArray();
                $eDistribution = new Marktjagd_Entity_Api_Distribution();
                $eDistribution->setCompanyId($aDistribution['company_id'])
                    ->setDistributionId($aDistribution['id'])
                    ->setTitle($aDistribution['title'])
                    ->setStoreCount($aDistribution['store_number']);
                $cDistribution->addElement($eDistribution);
            }
        } while (count($distributions) == $this->count);

        return $cDistribution;
    }

    public function findStoresByDistribution(int $companyId, ?string $distribution, bool $excludeDistribution = false, bool $visibleOnly = true)
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        $sPartner = new Marktjagd_Database_Service_Partner();
        $ePartner = $sPartner->findByCompanyId($companyId);

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironment($ePartner);

        $cStores = new Marktjagd_Collection_Api_Store();
        $cDistribution = $this->findDistributionsByCompany($companyId);

        $distributionParam = 'distribution_id';
        if ($excludeDistribution) {
            $distributionParam = 'distribution_id_exclude';
        }

        if (!$cDistribution) {
            $logger->log('Couldn\'t find any distribution for company ' . $companyId
                . ' and distribution ' . $distribution, Zend_Log::ERR);
        }

        $distributionId = false;
        foreach ($cDistribution->getElements() as $eDistribution) {
            /* @var $eDistribution Marktjagd_Entity_Api_Distribution */
            if ($eDistribution->getTitle() == $distribution) {
                $distributionId = $eDistribution->getDistributionId();
                continue;
            }
        }

        if ($distribution && !$distributionId) {
            $logger->log('Couldn\'t find distribution ' . $distribution . ' for company ' . $companyId, Zend_Log::ERR);
            return false;
        }

        $page = 1;
        do {
            $params = array(
                'with_offer_flags' => false,
                'company_id' => $companyId,
                'count' => $this->count,
                'page' => $page++
            );

            if ($distributionId) {
                $params[$distributionParam] = $distributionId;
            }

            if ($visibleOnly) {
                $params['status'] = StoreResource::STATUS_VISIBLE;
            }

            $stores = StoreResource::findAll(
                $params
            );

            if (Request::getInstance()->hasErrors()) {
                $logger->log('Error while getStores: company '
                    . $companyId . ' - '
                    . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR);
            }

            foreach ($stores as $store) {
                $aStore = $store->toArray();

                // Öffnungszeiten als string
                $storeHours = array();
                if (is_array($aStore['hours']) && array_key_exists('hour', $aStore['hours'])) {
                    foreach ($aStore['hours']['hour'] as $storeHour) {
                        $dayStr = $storeHour['day_from'];
                        if ($storeHour['day_from'] != $storeHour['day_to']) {
                            $dayStr .= "-" . $storeHour['day_to'];
                        }

                        $storeHours[] = $dayStr
                            . " "
                            . $storeHour['time_from']
                            . "-"
                            . $storeHour['time_to'];
                    }
                }

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setId($aStore['id'])
                    ->setStoreNumber($aStore['number'])
                    ->setTitle($aStore['title'])
                    ->setSubtitle($aStore['subtitle'])
                    ->setText($aStore['description'])
                    ->setStreet($aStore['street'])
                    ->setStreetNumber($aStore['street_number'])
                    ->setZipcode($aStore['zipcode'])
                    ->setCity($aStore['city'])
                    ->setLatitude($aStore['latitude'])
                    ->setLongitude($aStore['longitude'])
                    ->setPayment($aStore['payment'])
                    ->setWebsite($aStore['homepage'])
                    ->setEmail($aStore['email'])
                    ->setPhone($aStore['phone_number'])
                    ->setFax($aStore['fax_number'])
                    ->setStoreHours(implode(',', $storeHours))
                    ->setStoreHoursNotes($aStore['hours_text'])
                    ->setDistribution($distribution)
                    ->setParking($aStore['parking'])
                    ->setBarrierFree($aStore['barrier_free'])
                    ->setBonusCard($aStore['bonus_card'])
                    ->setSection($aStore['section'])
                    ->setService($aStore['service'])
                    ->setToilet($aStore['toilet']);

                $cStores->addElement($eStore);
            }
        } while (count($stores) == $this->count);

        return $cStores;
    }

    public function findActiveBrochuresByCompany(int $companyId)
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $retArr = array();
        $page = 1;
        do {
            $brochures = BrochureResource::findAll(
                array(
                    'company_id' => $companyId,
                    'time_constraint' => array('current', 'upcoming', 'future', 'expired'),
                    'status' => 'visible',
                    'count' => $this->count,
                    'page' => $page++,
                    'page_number' => '1',
                    'sort' => 'modified',
                    'order' => 'desc'
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

            foreach ($brochures as $brochure) {
                $aBrochure = $brochure->toArray();
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
            }
        } while (count($brochures) == $this->count);

        return $retArr;
    }

    public function findArticleById(int $companyId, string $id)
    {
        return [];
    }

    public function findStoresWithActiveBrochures(int $brochureId, int $companyId)
    {
        $retArr = false;

        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $page = 1;
        do {
            $brochures = StoreResource::findAll(
                array(
                    'company_id' => $companyId,
                    'brochure_id' => $brochureId,
                    'status' => 'visible',
                    'count' => $this->count,
                    'page' => $page++
                ));

            if (Request::getInstance()->hasErrors()) {
                $logger->log(
                    'Error while findActiveBrochuresByCompany: company '
                    . $companyId . ' - '
                    . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
                );
                return false;
            }

            foreach ($brochures as $brochure) {
                $aBrochure = $brochure->toArray();
                $retArr[$aBrochure['id']]['number'] = $aBrochure['number'];
                $retArr[$aBrochure['id']]['title'] = $aBrochure['title'];
                $retArr[$aBrochure['id']]['street'] = $aBrochure['street'];
                $retArr[$aBrochure['id']]['street_number'] = $aBrochure['street_number'];
                $retArr[$aBrochure['id']]['zipcode'] = $aBrochure['zipcode'];
                $retArr[$aBrochure['id']]['city'] = $aBrochure['city'];
                $retArr[$aBrochure['id']]['lng'] = $aBrochure['longitude'];
                $retArr[$aBrochure['id']]['lat'] = $aBrochure['latitude'];
            }
        } while (count($brochures) == $this->count);

        return $retArr;
    }

    public function findActiveArticlesByCompany(int $companyId)
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $retArr = array();
        $dateCreated = '';
        $retArr['lastModified'] = '';
        $page = 1;
        do {
            $articles = ArticleResource::findAll(
                array(
                    'company_id' => $companyId,
                    #'time_constraint' => array('current', 'upcoming', 'future', 'expired'),
                    'status' => 'visible',
                    'count' => $this->count,
                    'page' => $page++,
                    'sort' => 'modified',
                    'order' => 'desc'
                )
            );

            if (Request::getInstance()->hasErrors()) {
                $logger->log(
                    'Error while findActiveArticlesByCompany: company '
                    . $companyId . ' - '
                    . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
                );
                return false;
            }

            foreach ($articles as $article) {
                $aArticle = $article->toArray();
                if (!strlen($retArr['lastModified'])) {
                    $retArr['lastModified'] = $aArticle['datetime_modified'];
                    if (strtotime($dateCreated) < strtotime($aArticle['datetime_created'])) {
                        $dateCreated = $aArticle['datetime_created'];
                    }
                }
                $retArr[$aArticle['id']]['articleNumber'] = $aArticle['number'];
                $retArr[$aArticle['id']]['title'] = $aArticle['title'];
                $retArr[$aArticle['id']]['validFrom'] = $aArticle['datetime_from'];
                $retArr[$aArticle['id']]['validTo'] = $aArticle['datetime_to'];
                $retArr[$aArticle['id']]['visibleFrom'] = $aArticle['datetime_visible_from'];
                $retArr[$aArticle['id']]['visibleTo'] = $aArticle['datetime_visible_to'];
                $retArr[$aArticle['id']]['created'] = $aArticle['datetime_created'];
            }
        } while (count($articles) == $this->count);

        if (!strlen($retArr['lastModified'])) {
            $retArr['lastModified'] = $dateCreated;
        }

        return $retArr;
    }

    public function findAllStoresForCompany(int $companyId,string $status = 'visible')
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $retArr = array();
        $page = 1;
        do {
            $stores = StoreResource::findAll(
                array(
                    'company_id' => $companyId,
                    'count' => $this->count,
                    'page' => $page++,
                    'sort' => 'modified',
                    'order' => 'desc',
                    'with_offer_flags' => 'false',
                    'status' => $status
                )
            );

            if (Request::getInstance()->hasErrors()) {
                $logger->log(
                    'Error while findAllStoresForCompany: company '
                    . $companyId . ' - '
                    . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
                );
                return false;
            }
            foreach ($stores as $store) {
                $aStore = $store->toArray();

                $storeHours = array();
                if (is_array($aStore['hours']) && array_key_exists('hour', $aStore['hours'])) {
                    foreach ($aStore['hours']['hour'] as $storeHour) {
                        $dayStr = $storeHour['day_from'];
                        if ($storeHour['day_from'] != $storeHour['day_to']) {
                            $dayStr .= "-" . $storeHour['day_to'];
                        }

                        $storeHours[] = $dayStr
                            . " "
                            . $storeHour['time_from']
                            . "-"
                            . $storeHour['time_to'];
                    }
                }

                $retArr[$aStore['id']]['number'] = $aStore['number'];
                $retArr[$aStore['id']]['title'] = $aStore['title'];
                $retArr[$aStore['id']]['subtitle'] = $aStore['subtitle'];
                $retArr[$aStore['id']]['street'] = $aStore['street'];
                $retArr[$aStore['id']]['street_number'] = $aStore['street_number'];
                $retArr[$aStore['id']]['zipcode'] = $aStore['zipcode'];
                $retArr[$aStore['id']]['city'] = $aStore['city'];
                $retArr[$aStore['id']]['datetime_modified'] = $aStore['datetime_modified'];
                $retArr[$aStore['id']]['phone_number'] = $aStore['phone_number'];
                $retArr[$aStore['id']]['fax_number'] = $aStore['fax_number'];
                $retArr[$aStore['id']]['store_hours_notes'] = $aStore['hours_text'];
                $retArr[$aStore['id']]['email'] = $aStore['email'];
                $retArr[$aStore['id']]['text'] = $aStore['description'];
                $retArr[$aStore['id']]['payment'] = $aStore['payment'];
                $retArr[$aStore['id']]['website'] = $aStore['homepage'];
                $retArr[$aStore['id']]['parking'] = $aStore['parking'];
                $retArr[$aStore['id']]['barrier_free'] = $aStore['barrier_free'];
                $retArr[$aStore['id']]['bonus_card'] = $aStore['bonus_card'];
                $retArr[$aStore['id']]['section'] = $aStore['section'];
                $retArr[$aStore['id']]['service'] = $aStore['service'];
                $retArr[$aStore['id']]['toilet'] = $aStore['toilet'];

                if (count($storeHours)) {
                    $retArr[$aStore['id']]['store_hours'] = implode(',', $storeHours);
                } else {
                    $retArr[$aStore['id']]['store_hours'] = null;
                }

                if (count($store->getImages()) > 0) {
                    $retArr[$aStore['id']]['has_images'] = count($store->getImages());
                } else {
                    $retArr[$aStore['id']]['has_images'] = null;
                }
            }
        } while (count($stores) == $this->count);

        return $retArr;
    }

    public function findLastImport(int $companyId, string $type, string $status = 'done')
    {
        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $lastImport = json_decode(ImportResource::findAll(
            array(
                'company_id' => $companyId,
                'count' => 1,
                'type' => $type,
                'status' => $status,
                'order' => 'desc'
            ))->getRequest()->getResponseBody())->imports;

        if (property_exists($lastImport, 'import') && !is_null($lastImport->import)) {
            return $lastImport->import[0]->datetime_started;
        }
        return false;
    }

    public function findStoreByStoreId(int $storeId, int $companyId)
    {
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $store = StoreResource::find($storeId);

        if (Request::getInstance()->hasErrors()) {
            $errors = Request::getInstance()->getErrors();
            $firstError = $errors[0]->getArguments();
            if (!preg_match('#Not exist#', $firstError['message'])) {
                $logger->log(
                    'Error while findStoreByStoreId: store '
                    . $storeId . ' - '
                    . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
                );
            }
            return false;
        }
        return $store->toArray();
    }
    public function findStoreByStoreNumber(string $storeNumber, string $companyId = '')
    {
        throw new \BadMethodCallException('not implemented for Core3');
    }

    public function findStoreNumbersByPostcode(string $postcode, int $companyId)
    {
        throw new \BadMethodCallException('not implemented for Core3');
    }

    public function findStoresWithBrochures(int $companyId, string $timeConstraint = 'current')
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        $retArr = false;

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $page = 1;

        do {
            $stores = StoreResource::findAll(
                array(
                    'company_id' => $companyId,
                    'has_brochures' => '1',
                    'status' => 'visible',
                    'time_constraint' => $timeConstraint,
                    'count' => $this->count,
                    'page' => $page++
                ));

            if (Request::getInstance()->hasErrors()) {
                $logger->log(
                    'Error while findStoresWithBrochures: company '
                    . $companyId . ' - '
                    . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
                );
                return false;
            }

            foreach ($stores as $store) {
                $aStore = $store->toArray();
                $retArr[$aStore['id']]['number'] = $aStore['number'];
                $retArr[$aStore['id']]['title'] = $aStore['title'];
                $retArr[$aStore['id']]['street'] = $aStore['street'];
                $retArr[$aStore['id']]['street_number'] = $aStore['street_number'];
                $retArr[$aStore['id']]['zipcode'] = $aStore['zipcode'];
                $retArr[$aStore['id']]['city'] = $aStore['city'];
                $retArr[$aStore['id']]['lng'] = $aStore['longitude'];
                $retArr[$aStore['id']]['lat'] = $aStore['latitude'];
            }
        } while (count($stores) == $this->count);

        return $retArr;
    }

    public function findCompanyByCompanyId(int $companyId, $industryId = false)
    {
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $params = [
            'company_id' => $companyId,
            'status' => 'visible',
        ];
        if ($industryId !== false) {
            $params['industry_id'] = $industryId;
        }

        $companies = CompanyResource::findAll($params);

        if (Request::getInstance()->hasErrors()) {
            $logger->log(
                'Error while findCompanyByCompanyId: company '
                . $companyId . ' - '
                . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
            );
            return false;
        }

        foreach ($companies as $company) {
            $aCompany = $company->toArray();

            return $aCompany;
        }
    }

    public function findManufacturerTagByArticleId(int $companyId, int $articleId)
    {
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $tags = TagResource::findAll(
            array(
                "type" => 'article',
                'tag_type' => 'manufacturer',
                'type_id' => $articleId
            )
        );

        if (Request::getInstance()->hasErrors()) {
            $logger->log(
                'Error while findArticleByArticleNumber: company '
                . $companyId . ' - '
                . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
            );
            return false;
        }

        foreach ($tags as $tag) {
            $tmp = $tag->toArray();
            if (array_key_exists('title', $tmp)) {
                return $tmp['title'];
            }
        }
        return false;
    }

    public function findArticleByArticleNumber(int $companyId, string $articleNumber)
    {
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $article = ArticleResource::findAll(
            array(
                'company_id' => $companyId,
                'search' => $articleNumber,
                'time_constraint' => array('current', 'upcoming', 'future'),
                'with_tags' => 1
            )
        );

        if (Request::getInstance()->hasErrors()) {
            $logger->log(
                'Error while findArticleByArticleNumber: company '
                . $companyId . ' - '
                . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
            );
            return false;
        }

        $aArticle = array();
        foreach ($article as $singleArticle) {
            $aArticle = $singleArticle->toArray();
            $aArticle['image'] = $singleArticle->images->image[0]->dimensions->dimension[0]->url;

        }
        return $aArticle;
    }

    public function findUpcomingArticleByNumber(int $companyId, string $articleNumber)
    {
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $article = ArticleResource::findAll(
            [
                'company_id' => $companyId,
                'search' => $articleNumber,
                'time_constraint' => array('upcoming', 'future'),
                'with_tags' => 1,
            ]
        );

        if (Request::getInstance()->hasErrors()) {
            $logger->log(
                'Error while findArticleByArticleNumber: company '
                . $companyId . ' - '
                . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
            );
            return false;
        }

        $aArticle = [];
        foreach ($article as $singleArticle) {
            $aArticle = $singleArticle->toArray();
            $aArticle['image'] = $singleArticle->images->image[0]->dimensions->dimension[0]->url;

        }
        return $aArticle;
    }

    public function getActiveArticleCollection(int $companyId)
    {
        $logger = Zend_Registry::get('logger');

        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $cArticles = new Marktjagd_Collection_Api_Article();

        $page = 1;
        do {
            $articles = ArticleResource::findAll(
                array(
                    'company_id' => $companyId,
                    'time_constraint' => array('current', 'upcoming', 'future'),
                    'status' => 'visible',
                    'count' => $this->count,
                    'page' => $page++
                )
            );

            if (Request::getInstance()->hasErrors()) {
                $logger->log(
                    'Error while getActiveArticleCollection: company '
                    . $companyId . ' - '
                    . print_r(Request::getInstance()->getErrors(), true), Zend_Log::ERR
                );
                return false;
            }

            foreach ($articles as $article) {
                $aArticle = $article->toArray();
                $strImage = '';
                if (count($article->images->image)) {
                    foreach ($article->images->image as $singleImage) {
                        if (strlen($strImage)) {
                            $strImage .= ',';
                        }

                        $strImage .= $singleImage->dimensions->dimension[0]->url;
                    }
                }
                $eArticle = new Marktjagd_Entity_Api_Article();

                $eArticle->setArticleId($aArticle['id'])
                    ->setArticleNumber($aArticle['number'])
                    ->setTitle($aArticle['title'])
                    ->setText($aArticle['description'])
                    ->setEan($aArticle['ean'])
                    ->setPrice($aArticle['price'])
                    ->setShipping($aArticle['shipping'])
                    ->setSuggestedRetailPrice($aArticle['manufacturer_price'])
                    ->setArticleNumberManufacturer($aArticle['manufacturer_number'])
                    ->setUrl($aArticle['url'])
                    ->setSize($aArticle['size'])
                    ->setColor($aArticle['color'])
                    ->setAmount($aArticle['amount'])
                    ->setStart($aArticle['datetime_from'])
                    ->setEnd($aArticle['datetime_to'])
                    ->setVisibleStart($aArticle['datetime_visible_from'])
                    ->setVisibleEnd($aArticle['datetime_visible_to'])
                    ->setImage($strImage);

                $cArticles->addElement($eArticle, TRUE, 'complex', FALSE);
            }


        } while (count($articles) == $this->count);

        return $cArticles;
    }

    public function import(Marktjagd_Database_Entity_CrawlerConfig $eCrawlerConfig, Crawler_Generic_Response $response): Crawler_Generic_Response
    {
        $logger = Zend_Registry::get('logger');
        $type = $eCrawlerConfig->getCrawlerType()->getType();

        if ($type == 'brochures') {
            $type = 'pdfs';
        }

        $companyId = $eCrawlerConfig->getCompany()->getIdCompany();
        $behavior = $eCrawlerConfig->getCrawlerBehaviour()->getBehaviour();

        $sPartner = new Marktjagd_Database_Service_Partner();
        $ePartner = $sPartner->findByCompanyId($companyId);

        if ($response->getIsImport()) {
            $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            if($configCrawler->crawler->s3->active) {
                // For S3, the URL to the file is already in the response.
                $url = $response->getFileName();
            } else {
                $url = Marktjagd_Service_Output_File::generatePublicUrl($response->getFileName());
            }

            if (!$url) {
                $logger->log('Es konnte keine öffentliche URL für die Datei ' . $response->getFileName()
                    . ' im ' . ucwords($type) . '-Crawler für'
                    . ' Unternehmen ' . $eCrawlerConfig->getCompany()->getName() . ' (ID:' . $companyId . ')'
                    . ' erzeugt werden!', Zend_Log::ERR);
            }

            $apiClient = new Marktjagd_Entity_MarktjagdApi();
            $apiClient->setEnvironment($ePartner);

            // API-Aufruf
            $import = new ImportResource();

            if (empty($companyId)
                || empty($behavior)
                || empty($type)
                || empty($url)
            ) {
                $logger->log('Fehler beim Import in die API (' . $ePartner->getName() . '): Unternehmen '

                    . $eCrawlerConfig->getCompany()->getName() . ' (ID:' . $companyId . ')'
                    . ' - ' . print_r($eCrawlerConfig->toArray(), true)
                    . ' - ' . print_r($response, true), Zend_Log::ERR);
                $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE_ADD);
                return $response;
            }

            $import->setCompanyId($companyId);
            $import->setBehavior($behavior);
            $import->setType($type);
            $import->setUrl($url);
            $import->save();


            if ($import->hasErrors()){
                $logger->log('Fehler beim Hinzufügen des Imports für Unternehmen'
                    . $eCrawlerConfig->getCompany()->getName() . ' (ID:' . $companyId . ') aufgetreten'
                    . ' - ' . print_r($import->getErrors(), true), Zend_Log::ERR);
                $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE_ADD);
            } else {
                /** Import-Id speichern, um später den Status des Imports abfragen zu können */
                $apiResponse = $import->getRequest()->getResponse();
                $importId = (int) $apiResponse->import->id;
                $response->setImportId($importId);
                $response->setLoggingCode(Crawler_Generic_Response::IMPORT_PROCESSING);
            }
        }

        return $response;
    }

    public function findImportById(int $companyId, int $importId): array
    {
        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironmentByCompanyId($companyId);

        $result = ImportResource::find($importId, array('with_errors' => 1));
        if (!$result) throw new \RuntimeException("unable to find import $importId");

        $import = [
            'id' => $result->id,
            'status' => $result->status,
            'errors' => [],
        ];
        foreach ($result->import_errors as $error) {
            $import['errors'][] = [
                'record' => $error->record,
                'message' => $error->message,
            ];
        }
        return $import;
    }

    public function createStore(array $store): void
    {

    }

    public function findBrochureByBrochureNumberAndCompany(string $brochureNumber, int $companyId): ?array
    {
        return [];
    }

    public function createSalesRegionFromStoreNumbers(int $integrationId, array $storeNumbers): array
    {
        return [];
    }

    public function createSalesRegionForTheWholeCountry(int $integrationId, string $countryCode)
    {
        return [];
    }

    public function createSalesRegion(array $iprotoCreateSalesRegionRequest)
    {
        return [];
    }

    public function createBrochure(array $brochure): array
    {
        return [];
    }

    public function createBrochureImagesBatch(array $imageUrls): int
    {
        return 0;
    }

    public function getBrochureImagesByBatchId(string $batchId, int $maxBatchWaitTime = 512): array
    {
        return [];
    }

    public function findCompanyByName(int $ownerId, string $searchString): ?array
    {
        return [];
    }
}
