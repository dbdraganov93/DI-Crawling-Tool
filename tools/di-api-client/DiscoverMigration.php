<?php

require_once '../../scripts/index.php';
require_once ('MjApiClient.php');
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

class DiscoverMigration
{
    private $sApi;
    const TARGET_VERSION = 3;
    protected $_logger;

    /**
     * DiscoverMigration constructor.
     */
    public function __construct()
    {
        $this->sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $this->_logger = Zend_Registry::get('logger');
    }

    public function migrate() {

        /*
         * We decided to use the Customer CI Google sheet to get a list of all companyIds
         * where our customers have Discover brochures because we made it mandatory to
         * create a record in this sheet for every new Discover customer, including test companies
         * in our different stage environments.
         */
        $this->_logger->info('Requesting Customer CI Google Sheet');
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $customerCiSheet = $sGSRead->getFormattedInfos('18od4BgWPgd6cwocIkz4QlnJuqH3Lfi8quCQCl3JdklU', 'A1', 'R', 'Customer CI');
        $companyIds = [];
        foreach ($customerCiSheet as $customer) {
            if ($customer['company_id'] != 0) {
                $companyIds[] = $customer['company_id'];
            }
        }
        $this->_logger->info('List of companyIds: ' . implode(', ', $companyIds));

        /*
         * The next phase is a loop over all company ids.
         * Within this loop, we:
         * - request all active brochures
         * - filter those with an old layout string
         * - calculate an up-to-date layout string
         * - and update the brochure entity
         */
        foreach ($companyIds as $companyId) {
            $this->_logger->info('[MIGRATING - ' . $companyId . ']');
            $blender = new Blender($companyId);
            $this->_logger->info('Requesting discover brochures for companyId: ' . $companyId);
            $discoverBrochures = $this->sApi->findActiveBrochuresByCompanyWithDiscover($companyId);
            if ($discoverBrochures === false) {
                $this->_logger->info('No Discover brochure found for companyId: ' .$companyId);
                continue;
            }
            $this->_logger->info('Found ' . count($discoverBrochures) . ' active discover brochures for companyId: ' . $companyId);

            if (count($discoverBrochures) == 0) {
                $this->_logger->info('Continuing with next company...');
                continue;
            }

            /*
             * After querying all Discover brochures,
             * we need to filter those which are already on the
             * most recent layout version.
             */
            $this->_logger->info('[FILTERING] brochures with layout string older than ' . self::TARGET_VERSION);
            $filteredBrochures = array_filter($discoverBrochures, function ($v, $k) {
                $layoutVersion = $this->findLayoutVersionForBrochure($v);
                if ($layoutVersion < self::TARGET_VERSION) {
                    $this->_logger->info('[FILTERING] Brochure ID: ' . $k . ' - most recent layout version is ' . $layoutVersion);
                    return true;
                } else {
                    return false;
                }
            }, ARRAY_FILTER_USE_BOTH);
            $this->_logger->info('[FILTERING] Filtered brochure count: ' . count($filteredBrochures));

            if (count($filteredBrochures) == 0) {
                $this->_logger->info('Continuing with next company...');
                continue;
            }

            /*
             * At this point, the array filteredBrochures contains all the
             * Discover brochures for a specific companyId which need to
             * be migrated to the most recent layout version: self::TARGET_VERSION
             */
            foreach ($filteredBrochures as $brochureId => $brochureToUpdate) {
                $pagesForBlender = $this->preparePagesForBlender($brochureId, $brochureToUpdate);
                if ($pagesForBlender === false) {
                    continue;
                }
                $this->_logger->info('[BLENDING - ' . $brochureId . ']');
                $newLayoutString = $blender->blend($pagesForBlender);
                $this->_logger->info('[UPDATING - ' . $brochureId . ']');
                $ret = $this->updateBrochure($companyId, $brochureId, $newLayoutString);
                $this->_logger->info('[UPDATING - ' . $brochureId . '] SUCCESSFUL!');
                $this->_logger->info('[UPDATING - ' . $brochureId . '] Updated: ' . $ret->brochure->title . '; valid from: ' . $ret->brochure->datetime_from . ' until: ' . $ret->brochure->datetime_to);
            }
        }
        return true;
    }

    private function findLayoutVersionForBrochure($brochure)
    {
        $latestLayoutVersionInBrochure = 0;
        $layoutObject = json_decode($brochure['layout']);
        foreach ($layoutObject as $layoutVersion => $layoutString){
            $version = intval($layoutVersion);
            if ($version > $latestLayoutVersionInBrochure) {
                $latestLayoutVersionInBrochure = $version;
            }
        }
        return $latestLayoutVersionInBrochure;
    }

    private function preparePagesForBlender($brochureId, $brochure)
    {
        $this->_logger->info('[PREPARATION - ' . $brochureId . '] preparing product pages for Blender');
        $latestLayoutVersionInBrochure = $this->findLayoutVersionForBrochure($brochure);
        $layoutObject = json_decode($brochure['layout']);

        $latestLayoutStringInBrochure = null;
        foreach ($layoutObject as $layoutVersion => $layoutString) {
            if ($layoutVersion != $latestLayoutVersionInBrochure) {
                continue;
            } else {
                $latestLayoutStringInBrochure = $layoutString;
                break;
            }
        }

        if ($latestLayoutStringInBrochure === null) {
            $this->_logger->err('[PREPARATION - ' . $brochureId . '] parsing the layout FAILED. Skipping brochure');
            return false;
        }

        $this->_logger->info('[PREPARATION - ' . $brochureId . '] latest layout version is: ' . $latestLayoutVersionInBrochure);
        $this->_logger->debug('[PREPARATION - ' . $brochureId . '] brochure has ' . count($latestLayoutStringInBrochure->pages) . ' product pages');
        $pages = [];
        foreach ($latestLayoutStringInBrochure->pages as $pageNumber => $productPage) {
            $this->_logger->debug('[PREPARATION - ' . $brochureId . '] Page: ' . $pageNumber . ' with pageMetaphor: ' . $productPage->pageMetaphor);
            $page = ['pageMetaphor' => $productPage->pageMetaphor, 'articles' => []];
            foreach ($productPage->modules as $module) {
                foreach ($module->products as $product) {
                    if (empty($product->priority)) {
                        $prio = random_int(1, 3);
                    } else {
                        $prio = intval($product->priority);
                    }
                    $this->_logger->debug('[PREPARATION - ' . $brochureId . '] Adding product: ' . $product->id . ' with prio: ' . $prio);
                    $page['articles'][] = ['article_id' => $product->id, 'prio' => $prio];
                }
            }
            $pages[] = $page;
        }
        return $pages;
    }

    private function updateBrochure($companyId, $brochureId, $newLayoutString)
    {
        $sPartner = new Marktjagd_Database_Service_Partner();
        $ePartner = $sPartner->findByCompanyId($companyId);

        $host = $ePartner->getApiHost();
        $keyId = $ePartner->getApiKey();
        $secretKey = $ePartner->getApiPassword();

        if (empty($host) or empty($keyId) or empty($secretKey)) {
            $this->_logger->err('[UPDATING - ' . $brochureId . '] No partner config found for companyId: ' . $companyId . ' Skipping brochure');
            return false;
        }

        MjRestRequest::setHost($host);
        MjRestRequest::setKeyId($keyId);
        MjRestRequest::setSecretKey($secretKey);

        return $this->sendRequest('brochure/' . $brochureId, [], 'post', [
            'brochure' => [
                'layout' => $newLayoutString
            ]
        ]);
    }

    private function sendRequest(string $res, $par=[], $met='get', $req=null)
    {
        $mjRestRequest = new MjRestRequest($res, $par);
        if (!is_null($req)) {
            $mjRestRequest->setRequestBody(json_encode($req), MjRestRequest::CONTENT_TYPE_JSON);
        }

        $mjRestRequest->$met();
        $code = $mjRestRequest->getResponseStatusCode();

        if( $code<200 || $code>299 ){
            if($r = $mjRestRequest->getResponse()) $message = print_r($r,true);
            else $message = print_r($mjRestRequest->getResponseBody(), true);
            throw new Exception("$met $res failed ($code): ".$message);
        }
        return $mjRestRequest->getResponse();
    }
}

$discoverMigration = new DiscoverMigration();
$discoverMigration->migrate();
