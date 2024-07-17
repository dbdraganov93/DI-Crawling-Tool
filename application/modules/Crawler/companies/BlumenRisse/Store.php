<?php

/* 
 * Store Crawler für Blumen Risse (ID: 29003)
 */

class Crawler_Company_BlumenRisse_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.blumen-risse.de/';
        $searchUrl = $baseUrl . 'de/standorte-oeffnungszeiten/standorte.php';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();



        $storeListUrl = $searchUrl;
        $patternMoreStores = '#<a\s*class="pageNaviNextLink"\s*href="(.*?)"#is';
        while (true) {
            $this->_logger->info('öffne ' . $storeListUrl);
            $sPage->open($storeListUrl);
            $page = $sPage->getPage()->getResponseBody();

            $patternStoreDiv = '#<div[^>]*class="listEntryTitle"[^>]*>(.*?)</p>#is';
            if (!preg_match_all($patternStoreDiv, $page, $matchesStore)) {
                throw new Exception($companyId . ': no stores found at url ' . $storeListUrl);
            }

            foreach ($matchesStore[1] as $storeElement) {
                $eStore = new Marktjagd_Entity_Api_Store();

                if (preg_match('#<p>(.*?)<br/><br/>#is', $storeElement, $matchesAddress)) {
                    $aAdress = explode('<br/>', $matchesAddress[1]);
                    $eStore->setStreetAndStreetNumber($aAdress[0]);
                    $eStore->setZipcodeAndCity($aAdress[1]);
                }

                if (preg_match('#Tel\.\:\s*(.*?)<br/>#is', $storeElement, $matchPhone)) {
                    $eStore->setPhoneNormalized($matchPhone[1]);
                }

                if (preg_match('#href="(.*?)"#is', $storeElement, $matchWebsite)) {
                    $storeDetailUrl = $baseUrl . substr($matchWebsite[1], 1);
                    $eStore->setWebsite($storeDetailUrl);
                    $sPage->open($storeDetailUrl);
                    $detailPage = $sPage->getPage()->getResponseBody();

                    if (preg_match('#itemprop="openingHours"\s*content="(.*?)"#is', $detailPage, $matchOpenings)) {
                        $eStore->setStoreHoursNormalized($matchOpenings[1]);
                    }
                }

                $cStores->addElement($eStore);
            }

            if (preg_match($patternMoreStores, $page, $matchStoreLink)) {
                $storeListUrl = $searchUrl . $matchStoreLink[1];
            } else {
                break;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}