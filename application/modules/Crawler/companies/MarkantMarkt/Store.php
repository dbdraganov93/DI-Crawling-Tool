<?php

/* 
 * Store Crawler für Markant Markt (ID: 28979)
 */

class Crawler_Company_MarkantMarkt_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.mein-markant.de';
        $searchUrl = $baseUrl . '/mein-markt/unsere-maerkte/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*oMaerkte\s*=\s*(\[[^\]]+?\]);#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (json_decode($storeListMatch[1]) as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#tel:([^"]+?)"#';
            if (preg_match($pattern, $singleJStore->city, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $eStore->setStoreNumber($singleJStore->uid)
                    ->setSubtitle($singleJStore->name)
                    ->setStreetAndStreetNumber($singleJStore->street)
                    ->setZipcode($singleJStore->zip)
                    ->setCity($singleJStore->cityRaw)
                    ->setStoreHoursNormalized($singleJStore->openings)
                    ->setWebsite($baseUrl . $singleJStore->link)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lon);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}