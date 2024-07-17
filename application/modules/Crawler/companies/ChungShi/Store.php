<?php

/*
 * Store Crawler für Chung Shi (ID: 71724)
 */

class Crawler_Company_ChungShi_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.chung-shi.com/';
        $searchUrl = $baseUrl . 'index.php?lang=de&hn=probelaufen&sn2=haendlersuche'
                . '&cont=haendlersuche&lid=1&plz='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sGen = new Marktjagd_Service_Generator_Url();
        $count = 1;

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a\s*href="http\:\/\/www\.chung-shi\.com\/(de/adressen/hid-[0-9]+?/)"#';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                $this->_logger->info($companyId . ': no stores for zipcode: ' . $singleUrl);
                continue;
            }
            foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                $aUrlsToUse[] = $singleStoreUrl;
            }
        }
        
        foreach ($aUrlsToUse as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<div\s*id="inhaltsinboxlinks">\s*(.+?)</div>#s';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos list: ' . $storeDetailUrl);
                continue;
            }

            $pattern = '#<p[^>]*>\s*(.+?)\s*<#';
            if (!preg_match_all($pattern, $storeInfoMatch[1], $storeInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos from list: ' . $storeDetailUrl);
                continue;
            }

            $eStore->setSubtitle(trim(strip_tags($storeInfoMatches[1][1])) . ' ' . trim(strip_tags($storeInfoMatches[1][2])))
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $storeInfoMatches[1][3])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $storeInfoMatches[1][3])))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $storeInfoMatches[1][4]))
                    ->setCity($sAddress->extractAddressPart('city', $storeInfoMatches[1][4]))
                    ->setPhone($sAddress->normalizePhoneNumber($storeInfoMatches[1][5]))
                    ->setFax($sAddress->normalizePhoneNumber($storeInfoMatches[1][6]))
                    ->setWebsite(trim(strip_tags($storeInfoMatches[1][7])))
                    ->setEmail(trim(strip_tags($storeInfoMatches[1][8])));

            $cStores->addElement($eStore, TRUE);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
