<?php

/**
 * Store Crawler für Konsum Leipzig (ID: 28873)
 */
class Crawler_Company_KonsumLeipzig_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.konsum-leipzig.de/';
        $searchUrl = $baseUrl . 'einkaufen/filialen';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<b[^>]*>\s*([0-9]{5})\s*</b>\s*(.+?)\s*</p>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 0; $i < count($storeMatches[0]); $i++) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $aData = preg_split('#\s*(<[^>]*>|·)\s*#', $storeMatches[2][$i]);
            $aAddress = preg_split('#\s*,\s*#', $aData[1]);
            
            $eStore->setZipcode($storeMatches[1][$i])
                    ->setCity($aData[0])
                    ->setStreetAndStreetNumber(end($aAddress))
                    ->setPhoneNormalized($aData[2])
                    ->setStoreHoursNormalized($aData[3]);
            
            if (count($aAddress) > 1) {
                $eStore->setSubtitle($aAddress[0]);
            }
                        
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
