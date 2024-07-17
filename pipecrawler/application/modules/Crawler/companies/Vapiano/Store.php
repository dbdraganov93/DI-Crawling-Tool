<?php

/* 
 * Store Crawler für Vapiano (ID: 70869)
 */

class Crawler_Company_Vapiano_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://de.vapiano.com';
        $searchUrl = $baseUrl . '/de/restaurants/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*restaurants\s*=\s*\'(.+?)\',#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (json_decode($storeListMatch[1]) as $singleJStore) {
            if (!preg_match('#DE#', $singleJStore->countryCode)) {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->zip)
                    ->setLongitude($singleJStore->longitude)
                    ->setLatitude($singleJStore->latitude)
                    ->setStreetAndStreetNumber($singleJStore->address2)
                    ->setStoreNumber($singleJStore->storeId)
                    ->setEmail($singleJStore->email)
                    ->setPhoneNormalized($singleJStore->telephone)
                    ->setWebsite($baseUrl . $singleJStore->detailLink)
                    ->setToilet(TRUE);
            
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#ffnungszeiten\s+Restaurant(.+?)</p#';
            if (preg_match($pattern, $page, $storeHoursListMatch)) {
                $pattern = '#<time[^>]*>([^<]+?)<#';
                if (preg_match_all($pattern, $storeHoursListMatch[1], $storeHoursMatches)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings(implode(',', $storeHoursMatches[1]), 'text', TRUE));
                }
            }
            
            $pattern = '#(Öffnungszeiten\s+Küche:)(.+?)</p#';
            if (preg_match($pattern, $page, $storeHoursNotesListMatch)) {
                $pattern = '#<time[^>]*>([^<]+?)<#';
                if (preg_match_all($pattern, $storeHoursNotesListMatch[2], $storeHoursMatches)) {
                    $eStore->setStoreHoursNotes($storeHoursNotesListMatch[1] . ' ' . implode(', ', $storeHoursMatches[1]));
                }
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}