<?php

/**
 * Store Crawler für Weltbild (ID: 28894)
 */
class Crawler_Company_Weltbild_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.weltbild.de/';
        $searchUrl = $baseUrl . 'konto/filialfinder';
        $sPage = new Marktjagd_Service_Input_Page(true);
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        

        $pattern = '#<ul\s*property="chainstoreList"[^>]*>(.+?)</ul#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<li[^>]*class="store"(.+?)>#s';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#data\-([^=]+?)="([^"]*?)"#is';
            if (!preg_match_all($pattern, $singleStore, $storeInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get store infos for: ' . $singleStore);
            }
            
            foreach ($storeInfoMatches[1] as $idx => $storeData){
                $storeVal = $storeInfoMatches[2][$idx];
                switch ($storeData){
                        case "id": 
                            $eStore->setStoreNumber($storeVal);
                            break;
                        
                        case "lat":
                            $eStore->setLatitude($storeVal);
                            break;
                        
                        case "lng":
                            $eStore->setLongitude($storeVal);
                            break;
                        
                        case "street":
                            $eStore->setStreetAndStreetNumber($storeVal);
                            break;
                        
                        case "zip":
                            $eStore->setZipcode($storeVal);
                            break;
                        
                        case "city":
                            $eStore->setCity($storeVal);                              
                            break;
                        
                        case "phone":
                            $eStore->setPhoneNormalized($storeVal);                               
                            break;
                        
                        case "hours":
                            $hours = preg_split('#</p>#', html_entity_decode($storeVal));                           
                            $eStore->setStoreHoursNormalized($hours[0]);                          
                            break;
                        
                        case "description":
                            $eStore->setSubtitle($storeVal);
                            break;
                }
            }
                                    
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
