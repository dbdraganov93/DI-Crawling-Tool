<?php

/* 
 * Store Crawler für Bingo CH (ID: 72213)
 */

class Crawler_Company_BingoCh_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://de.bingo-shoes.ch/';
        $searchUrl = $baseUrl . 'filialfinder';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#FilialFinder\.shops\.push\(([^\)]+?)\)#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $aStoreData = json_decode($singleStore);

            if (!preg_match('#CH#', $aStoreData->country)
                    || $aStoreData->active != 1) {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($aStoreData->id)
                    ->setStreetAndStreetNumber(strip_tags($aStoreData->address1), 'CH')
                    ->setZipcode($aStoreData->zipcode)
                    ->setCity($aStoreData->city)
                    ->setLatitude($aStoreData->latitude)
                    ->setLongitude($aStoreData->longitude)
                    ->setPhoneNormalized($aStoreData->phone)
                    ->setStoreHoursNormalized($aStoreData->hours)
                    ->setWebsite($aStoreData->url);
            
            if (preg_match_all('#(Do\s+(\d{2}:\d{2})-\d{2}:\d{2})#', $eStore->getStoreHours(), $thursdayMatches)
                    && count($thursdayMatches[1]) == 2
                    && $thursdayMatches[2][0] == $thursdayMatches[2][1]) {
                $eStore->setStoreHours(preg_replace('#(Mi[^\,]+?),\s*Do\s*[^\,]+?,\s*(Do)#', '$1, $2', $eStore->getStoreHours()));
            }
                        
            $cStores->addElement($eStore);
        }
        
        return $this->getResponse($cStores, $companyId);
    }
}