<?php

/*
 * Store Crawler für stader Saatzucht (ID: 71488)
 */

class Crawler_Company_Stader_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.stader-saatzucht.de/';
        $searchUrl = $baseUrl . 'standorte-a-z-standortsuche.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div\s*class="navi-standorte"[^>]*>(.+?)<footer#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        $pattern = '#<li\s*class="standorte[^>]*>\s*<a\s*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleUrl) {
            if (preg_match('#verwaltung#', $singleUrl)) {
                continue;
            }
            $storeDetailUrl = $baseUrl . $singleUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<h5[^>]*>(.+?)</h5>\s*<table[^>]*>(.+?)</table>\s*</div>#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores: ' . $storeDetailUrl);
                continue;
            }

            for ($i = 0; $i < count($storeMatches[0]); $i++) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $pattern = '#<td[^>]*>(.{3,}?)</td#';
                if (!preg_match_all($pattern, $storeMatches[2][$i], $storeInfoMatches)) {
                    $this->_logger->err($companyId . ': unable to get any store infos: ' . $storeDetailUrl);
                    continue;
                }
                $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeInfoMatches[1][0]);
                $aContact = preg_split('#\s*<br[^>]*>\s*#', end($storeInfoMatches[1]));

                $pattern = '#ffnungszeiten(.+?)</tbody#s';
                if (preg_match($pattern, $storeMatches[2][$i], $storeHoursMatch)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
                }

                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                        ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                        ->setPhone($sAddress->normalizePhoneNumber($aContact[0]))
                        ->setFax($sAddress->normalizePhoneNumber($aContact[1]))
                        ->setTitle(strip_tags($storeMatches[1][$i]));

                if (count($aContact) == 3) {
                    $eStore->setEmail($sAddress->normalizeEmail($aContact[2]));
                }
                
                if (strlen($eStore->getTitle()) > 50) {
                    continue;
                }
                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
