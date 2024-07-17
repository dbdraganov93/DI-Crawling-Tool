<?php

/*
 * Store Crawler für Unterwegs Outdoor Shop (ID: 71450)
 */

class Crawler_Company_Unterwegs_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.unterwegs.biz/';
        $searchUrl = $baseUrl . 'unternehmen.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*id="shop-map"[^>]*>(.+?)</div#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($storeUrlMatches[1] as $singleStoreLink) {
            $sPage->open($singleStoreLink);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#itemprop="([^"]+?)"[>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $storeLink);
                continue;
            }
            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStreetAndStreetNumber($aInfos['streetAddress'])
                    ->setCity($aInfos['addressLocality'])
                    ->setZipcode($aInfos['postalCode'])
                    ->setPhoneNormalized($aInfos['telephone'])
                    ->setEmail($aInfos['email'])
                    ->setStoreHoursNormalized($aInfos['openingHours'])
                    ->setWebsite($singleStoreLink);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
