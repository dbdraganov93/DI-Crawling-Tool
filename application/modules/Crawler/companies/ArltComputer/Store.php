<?php

/*
 * Store Crawler für Artl Computer (ID: 68971)
 */

class Crawler_Company_ArltComputer_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.arlt.com/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#href=\"([^\"]+?detailfilialen[^=]+?)=([^\"]+?)\"#i';
        if (!preg_match_all($pattern, $page, $matches)) {
            throw new Exception('Company ID ' . $companyId . ': could not get store urls from ' . $baseUrl);
        }

        $aStoreUrls = array();
        foreach ($matches[2] as $urlKey) {
            if (!array_key_exists($urlKey, $aStoreUrls)) {
                $aStoreUrls[$urlKey] = $matches[1][$urlKey] . '=' . $urlKey;
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreUrls as $key => $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#itemprop=\"streetAddress[^>]+?>([^<]+?)<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                throw new Exception('Company ID ' . $companyId . ': could not get store address on page: ' . $singleStoreUrl);
            }

            $pattern = '#itemprop=\"addressLocality[^>]+?>([^<]+?)<#i';
            if (!preg_match($pattern, $page, $cityMatch)) {
                throw new Exception('Company ID ' . $companyId . ': could not get store city/zip on page: ' . $singleStoreUrl);
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#itemprop=\"telephone[^>]+?([^<]+?)<#i';
            if (preg_match($pattern, $page, $telMatch)) {
                $eStore->setPhoneNormalized($telMatch[1]);
            }

            $pattern = '#<div[^>]+?storeOpen\">.+?<br\s*\/>\s*Fax\:\s*([^<]+?)<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $pattern = '#<a\s*href=\"mailto\:([^\"]+?)\"#i';
            if (preg_match($pattern, $page, $emailMatch)) {
                $eStore->setEmail($emailMatch[1]);
            }

            $pattern = '#class=\"storeOpenTimesDay\">([^<]+?)<[^<]+?<span>([^<]+?)<#i';
            if (preg_match_all($pattern, $page, $openingHoursMatch)) {
                $sOpeningHours = '';
                for ($i = 0; $i < count($openingHoursMatch[1]); $i++) {
                    if (strlen($sOpeningHours)) {
                        $sOpeningHours .= ',';
                    }
                    $sOpeningHours .= $openingHoursMatch[1][$i] . ' ' . $openingHoursMatch[2][$i];
                }

                $eStore->setStoreHoursNormalized($sOpeningHours);
            }

            $pattern = '#class=\"storepicture\".+?href=\"([^\"]+?)\"#';
            if (preg_match($pattern, $page, $imgMatch)) {
                $eStore->setImage($imgMatch[1]);
            }

            $eStore->setZipcodeAndCity($cityMatch[1])
                    ->setStreetAndStreetNumber($addressMatch[1])
                    ->setStoreNumber($key)
                    ->setWebsite($singleStoreUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
