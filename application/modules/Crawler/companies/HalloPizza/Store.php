<?php

/**
 * Storecrawler für Hallo Pizza (ID: 28991)
 */
class Crawler_Company_HalloPizza_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.hallopizza.de/';
        $storeListUrl = $baseUrl . 'bestellung';
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage = new Marktjagd_Service_Input_Page();
        $oPage = $sPage->getPage();
        $oPage->setUseCookies(true);
        $sPage->setPage($oPage);

        $sPage->open($storeListUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*>\s*<a [^>]*href="/([^"]+)"[^>]*>\s*A\s*</a>#';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('unable to get storelist link: ' . $storeListUrl);
        }

        $storeListUrl = $baseUrl . $match[1];
        $sPage->open($storeListUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class=[^>]*shopBlock[^>]*>(.+?)<div[^>]*class="extraTexts[^>]*>#';

        if (!preg_match_all($pattern, $page, $matchStores)) {
            throw new Exception('unable to get stores: ' . $storeListUrl);
        }

        foreach ($matchStores[1] as $matchStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            if (preg_match('#<div[^>]*class="zipTown"[^>]*>(.+?)</div>#', $matchStore, $matchCity)) {
                $eStore->setZipcodeAndCity($matchCity[1]);
            }

            if (preg_match('#<div[^>]*class="addrData"[^>]*>(.+?)</div>\s*<div[^>]*class="addrData"[^>]*>(.+?)</div>#',
                    $matchStore,
                $matchStreetPhone)
            ) {
                $eStore->setStreetAndStreetNumber($matchStreetPhone[1]);
                $eStore->setPhoneNormalized($matchStreetPhone[2]);
            }

            if (preg_match('#<div[^>]*class=\'oeffnungszeiten\'[^>]*>(.+?)<div[^>]*class="clear"#', $matchStore, $matchOpenings)) {
                $sOpenings = strip_tags($matchOpenings[1]);
                $sOpenings = preg_replace('#Uhr#', 'Uhr, ', $sOpenings);
                $sOpenings = preg_replace('#\,\s*Feiertags#', '', $sOpenings);
                $sOpenings = preg_replace('#\s*\,\s*#', '+', $sOpenings);
                $sOpenings = str_replace('00:00', '24:00', $sOpenings);
                $eStore->setStoreHoursNormalized($sOpenings);
            }

            if (preg_match('#<a[^>]*href="/(.*?AZ\-Liste)"#is', $matchStore, $matchUrl)) {
                $eStore->setWebsite($baseUrl . $matchUrl[1]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}