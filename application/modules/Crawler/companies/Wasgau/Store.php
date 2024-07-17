<?php

/*
 * Store Crawler für Wasgau (ID: 24949)
 */

class Crawler_Company_Wasgau_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.wasgau-ag.de/';
        $searchUrl = $baseUrl . 'finden-sie-ihren-wasgau-markt/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="mpfy-mll-location"(.+?)</div>\s*</div>\s*</div#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception ($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#<strong[^>]*>\s*([^\|]+?)\s*\|\s*([^<]+?)\s*,\s*(\d{5})\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#data-id="([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $idMatch)) {
                $eStore->setStoreNumber($idMatch[1]);
            }
            
            $pattern = '#Telefon:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#ffnungszeiten\s*</b>\s*<br[^>]*>\s*([A-Z][a-z](\s+|-)[^<]+?<(br[^>]*|/p)>\s*)+#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[0]);
            }
            
            $pattern = '#data-mpfy-value="\d+"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match_all($pattern, $singleStore, $serviceMatches)) {
                $eStore->setService(implode(', ', $serviceMatches[1]));
            }
            
            $pattern = '#Bäckerei:\s*(.+?<(br[^>]*|/p)>\s*)+#';
            if (preg_match($pattern, $singleStore, $storeHoursNotesMatch)) {
                $eStore->setStoreHoursNotes('Öffnungszeiten ' . trim(strip_tags(preg_replace('#(\d)<[^>]*>([A-Z])#', '$1, $2', $storeHoursNotesMatch[0]))));
            }
            
            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $eStore->setZipcode($addressMatch[3])
                    ->setCity($addressMatch[2])
                    ->setStreetAndStreetNumber($addressMatch[1]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}