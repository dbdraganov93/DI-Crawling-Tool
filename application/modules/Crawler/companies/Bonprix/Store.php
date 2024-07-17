<?php

/**
 * * Storecrawler für Bonprix (ID: 28985)

 *
 * Class Crawler_Company_Bonprix_Store
 */
class Crawler_Company_Bonprix_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $baseUrl = 'http://www.bonprix.de';
        $storeFinderUrl = $baseUrl . '/service/filialen/';

        $aUrlsCity = array();
        $servicePage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $cStore = new Marktjagd_Collection_Api_Store();

        $servicePage->open($storeFinderUrl);
        $sPage = $servicePage->getPage()->getResponseBody();

        $patternSelect = '#<select[^>]*name="bundeslandSelect"[^>]*>(.*?)</select>#is';
        $this->_logger->log('retrieving store links', Zend_Log::INFO);
        if (preg_match($patternSelect, $sPage, $matchCounty)) {
            $patternUrls = '#<option[^>]*value="(\/service.*?)"[^>]*>#is';
            if (preg_match_all($patternUrls, $matchCounty[1], $matchUrls)) {
                foreach ($matchUrls[1] as $relativeStoreUrl)  {
                    if ($relativeStoreUrl == '/service/filialen/hamburg/'
                        || $relativeStoreUrl == '/service/filialen/berlin/'
                        || $relativeStoreUrl == '/service/filialen/bremen/'
                        || $relativeStoreUrl == '/service/filialen/saarland/')
                    {
                        $aUrlsCity[$relativeStoreUrl] = $relativeStoreUrl;
                        continue;
                    }

                    $storeUrl = $baseUrl . $relativeStoreUrl;
                    $servicePage = new Marktjagd_Service_Input_Page();
                    $servicePage->open($storeUrl);
                    $pageCounty = $servicePage->getPage()->getResponseBody();

                    $patternCityPage = '#<a[^>]*href="(' . $relativeStoreUrl .  '[^"]+)"[^>]*>#is';
                    if (preg_match_all($patternCityPage, $pageCounty, $matchStore)) {
                        foreach ($matchStore[1] as $storeUrl) {
                            $aUrlsCity[$storeUrl] = $storeUrl;
                        };
                    }
                }
            }
        }

        $this->_logger->log('crawling city urls', Zend_Log::INFO);
        foreach ($aUrlsCity as $storeUrl) {
            $storeUrl = $baseUrl . $storeUrl;

            $this->_logger->log('crawling city url:' . $storeUrl, Zend_Log::INFO);
            $servicePage->open($storeUrl);
            $sPage = $servicePage->getPage()->getResponseBody();

            $patternStoreBox = '#<div[^>]*class="store_box"[^>]*>(.*?)</div>#is';
            if (!preg_match($patternStoreBox, $sPage, $matchStoreBox)) {
                $this->_logger->log('can\'t get store infos for store :' . $storeUrl, Zend_Log::ERR);
                echo 'can\'t get store infos for store :' . $storeUrl;
                continue;
            }

            $patternAddress = '#<h2>Adresse:</h2>(.*?)<h2>#is';
            if (!preg_match($patternAddress, $matchStoreBox[1], $matchAddress)) {
                $this->_logger->log('can\'t get store address for store :' . $storeUrl, Zend_Log::ERR);
                echo 'can\'t get store address for store :' . $storeUrl;
                continue;
            }

            $aAddressParts = explode(',', $matchAddress[1]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreet($sAddress->extractAddressPart('street', $aAddressParts[(count($aAddressParts)-2)]))
                   ->setStreetNumber($sAddress->extractAddressPart('streetNumber', $aAddressParts[(count($aAddressParts)-2)]))
                   ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddressParts[(count($aAddressParts)-1)]))
                   ->setCity($sAddress->extractAddressPart('city', $aAddressParts[(count($aAddressParts)-1)]));

            if ((count($aAddressParts)-1) == 3) {
                $eStore->setSubtitle($aAddressParts[0]);
            }

            $patternTimes = '#<h2>Öffnungszeiten:</h2>(.*?)<h2>#is';
            if (preg_match($patternTimes, $matchStoreBox[1], $matchTimes)) {
                $eStore->setStoreHoursNormalized($matchTimes[1], 'table');
            }

            $patternSection = '#<h2>Unser\sSortiment\-Angebot:</h2><p>(.*?)</p>#is';
            if (preg_match($patternSection, $matchStoreBox[1], $matchSection)) {
                $eStore->setSection($matchSection[1]);
            }
            
            $cStore->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}