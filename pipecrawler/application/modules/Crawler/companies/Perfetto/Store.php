<?php

/**
 * Store Crawler für Perfetto Karstadt Feinkostmarkt (ID: 28996)
 */
class Crawler_Company_Perfetto_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://karstadt-lebensmittel.de/';
        
        $searchUrl = $baseUrl;
        
        $sPage = new Marktjagd_Service_Input_Page();
        $cStore = new Marktjagd_Collection_Api_Store();

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }

        $page = $sPage->getPage()->getResponseBody();
                
        if (!preg_match_all('#data-url="(https://karstadt-lebensmittel.de/store/ajax/[0-9]+)"#', $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get store urls');
        }

        foreach ($storeMatches[1] as $storeUrl){
            if (!$sPage->open($storeUrl)) {
                throw new Exception($companyId . ': unable to open store list page ' . $storeUrl);
            }
            
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            if (preg_match('#<br[^>]*>(.+?)<br[^>]*>(.+?)</p>\s*<h2>Öffnungszeiten</h2>\s*<p>(.+?)</p>#', $page, $match)){
                $eStore->setStreetAndStreetNumber($match[1])
                        ->setZipcodeAndCity($match[2])
                        ->setStoreHoursNormalized($match[3]);
            }

            if (preg_match('#>Telefon[^<]*</strong>(.+?)<#', $page, $match)){
                $eStore->setPhoneNormalized($match[1]);
            }
            
            if (preg_match('#>Telefax[^<]*</strong>(.+?)<#', $page, $match)){
                $eStore->setPhoneNormalized($match[1]);
            }

            $cStore->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
