<?php

/**
 * Store Crawler für Robin Look (ID: 71081)
 */
class Crawler_Company_RobinLook_Store extends Crawler_Generic_Company
{
    public function crawl($companyId) {
        $baseUrl = 'http://robinlook.de';
        $searchUrl = $baseUrl . '/filialen';
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<span[^>]*class="f-item"[^>]*>(.*?)</span>#';
        if (!preg_match_all($pattern, $page, $stores)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStore = new Marktjagd_Collection_Api_Store();

        foreach ($stores[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<div\s*class="filial-address"\s*>\s*<h3>([^<]+)</h3>.*?<p>\s*(.*?)\s*</p>\s*</div>\s*<p[^>]*>(.*?)</p>\s*(<p>(.*?)<br[^>]*>)*#';
            if (!preg_match($pattern, $singleStore, $storeInfos)) {
                continue;
            }

            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeInfos[2]);
            $countElements = count($aAddress);

            $eStore->setPhoneNormalized($aAddress[$countElements-1]);
            $eStore->setZipcodeAndCity($aAddress[$countElements-2]);

            if (!$eStore->getZipcode()) {
                $countElements--;
                $eStore->setZipcodeAndCity($aAddress[$countElements-2]);
            }
            
            $eStore->setStreetAndStreetNumber($aAddress[$countElements-3]);
            if ($aAddress[$countElements-3] == 'S-Bahnhof Köpenick'
                || $aAddress[$countElements-3] == 'U-Bhf. Kaiserin-Augusta-Straße'
            ) {
                $eStore->setStreetAndStreetNumber($aAddress[$countElements-4]);
            }

            $eStore->setTitle('Robin Look');
            $eStore->setSubtitle($storeInfos[1]);

            $eStore->setStoreHoursNormalized($storeInfos[3]);
            if ($eStore->getCity() == 'Potsdam' && $eStore->getStreet() == 'Breite Straße') {
                $eStore->setStoreHoursNormalized($storeInfos[5]);
            }

            $cStore->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
