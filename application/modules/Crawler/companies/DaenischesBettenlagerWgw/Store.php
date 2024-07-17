<?php

/* 
 * Store Crawler für Dänisches Bettenlager WGW (ID: 72279)
 */

class Crawler_Company_DaenischesBettenlagerWgw_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://com.daenischesbettenlager.at/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $pageNo = 0;
        $searchUrl = $baseUrl . 'lb/unsere-filialen/filialsuche/seite/' . $pageNo
                . '.html?tx_dblfilialen_pi1%5Bplzort%5D=1010&'
                . 'tx_dblfilialen_pi1%5Bcat%5D=0&tx_dblfilialen_pi1%5Bplz%5D=1010&'
                . 'tx_dblfilialen_pi1%5Bort%5D=Wien';
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#</a>\s*\.\.\.\s*<a[^>]*href="[^"]+?seite\/(\d+)#';
        if (!preg_match($pattern, $page, $lastPageMatch)) {
            throw new Exception ($companyId . ': unable to get last page.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        for ($pageNo = 0; $pageNo <= $lastPageMatch[1]; $pageNo++) {
            $searchUrl = $baseUrl . 'lb/unsere-filialen/filialsuche/seite/' . $pageNo
                    . '.html?tx_dblfilialen_pi1%5Bplzort%5D=1010&'
                    . 'tx_dblfilialen_pi1%5Bcat%5D=0&tx_dblfilialen_pi1%5Bplz%5D=1010&'
                    . 'tx_dblfilialen_pi1%5Bort%5D=Wien';
            
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#var\s*infoTabs\s*=\s*\[(.+?);\s*var\s*marker#s';
            if (!preg_match_all($pattern, $page, $storeInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get store infos from page no ' . $pageNo);
                continue;
            }
            
            foreach ($storeInfoMatches[1] as $singleStore) {
                $pattern = '#</strong>\s*<br[^>]*>\s*<br[^>]*>\s*([^<]+?)\s*<[^>]*>(\s*[^<]+?<[^>]*>\s*)*\s*(\d{4}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#fon\s*:\s*([^<]+?)\s*<#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }
                
                $pattern = '#fax\s*:\s*([^<]+?)\s*<#';
                if (preg_match($pattern, $singleStore, $faxMatch)) {
                    $eStore->setFaxNormalized($faxMatch[1]);
                }
                
                $pattern = '#ffnungszeiten(.+?)<a#';
                if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }
                
                $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>\s*zur\s*detailseite#i';
                if (preg_match($pattern, $singleStore, $websiteMatch)) {
                    $eStore->setWebsite($baseUrl . $websiteMatch[1]);
                }
                
                $pattern = '#new\s*GLatLng\(\'([^\']+?)\',\'([^\']+?)\'#';
                if (preg_match($pattern, $singleStore, $geoMatch)) {
                    $eStore->setLatitude($geoMatch[1])
                            ->setLongitude($geoMatch[2]);
                }
                
                $eStore->setAddress($addressMatch[1], $addressMatch[3]);
                                                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}