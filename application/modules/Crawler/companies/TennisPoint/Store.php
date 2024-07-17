<?php

/*
 * Store Crawler für tennis point (ID: 71841)
 */

class Crawler_Company_TennisPoint_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.tennis-point.de/';
        $searchUrl = $baseUrl . 'stores/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="store\-container[^>]*>(.+?)</div>#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#p[^>]*>\s*([^<]+?)\s*<br[^>]*>\s*([0-9]{5}[^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $storeAddressMatch)) {
                $this->_logger->info($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<img\s*src="([^"]+?\.jpg)"#';
            if (!preg_match($pattern, $singleStore, $storeImageMatch)) {
                $eStore->setImage(preg_replace('#^\/(.+)#', $baseUrl . '$1', $storeImageMatch[1]));
            }

            $pattern = '#fon(.+?)<br#';
            if (preg_match($pattern, $singleStore, $storePhoneMatch)) {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }

            $pattern = '#fax(.+?)<br#';
            if (preg_match($pattern, $singleStore, $storeFaxMatch)) {
                $eStore->setFaxNormalized($storeFaxMatch[1]);
            }

            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $storeMailMatch)) {
                $eStore->setEmail($storeMailMatch[1]);
            }

            $pattern = '#ffnungszeiten(.+)#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $eStore->setAddress($storeAddressMatch[1], $storeAddressMatch[2]);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
