<?php

/* 
 * Store Crawler für Telepoint (ID: 29024)
 */

class Crawler_Company_Telepoint_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.telepoint.de/';
        $searchUrl = $baseUrl . 'filialen';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        if (!preg_match('#<div[^>]*id="branches"[^>]*>(.+?)</div>#', $page, $branchesMatch)){
            throw new Exception('cannot find branches area on ' . $searchUrl);
        }
                
        if (!preg_match_all('#<tr>\s*<td>(.+?)<td>(.+?)<td>(.+?)<td>\s*<img[^>]*src="([^"]+)"#is', $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores');
        }
     
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[0] as $idx => $storeInfo) {
            if (preg_match('#media\s*@\s*home#', $storeMatches[1][$idx])){
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $storeMatches[1][$idx] = preg_replace('#<br[^>]*>#', '###', $storeMatches[1][$idx]);
            $storeMatches[1][$idx] = preg_replace('#<[^>]*>#', '', $storeMatches[1][$idx]);
                
            $addressLines = preg_split('-###-', $storeMatches[1][$idx]);
         
            $eStore->setWebsite($searchUrl);
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $addressLines[count($addressLines)-2]))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[count($addressLines)-2]))
                    ->setCity($sAddress->extractAddressPart('city', $addressLines[count($addressLines)-1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[count($addressLines)-1]));
            
            if (count($addressLines) == 4){
                $eStore->setSubtitle($addressLines[1]);
            }            
            
            $eStore->setStoreHours($sTimes->generateMjOpenings($storeMatches[2][$idx]));
            
            if (preg_match('#tel([^<]+)<#i', $storeMatches[3][$idx], $match)){
                $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
            }
            
            if (preg_match('#fax([^<]+)<#i', $storeMatches[3][$idx], $match)){
                $eStore->setFax($sAddress->normalizePhoneNumber($match[1]));
            }
            
            $eStore->setImage($storeMatches[4][$idx]);

            if ($eStore->getZipcode() != '26135'){
                $eStore->setDistribution('Werbebeilage');
            }            
            
            $cStores->addElement($eStore);
        }        
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}