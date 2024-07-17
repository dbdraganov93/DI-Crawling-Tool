<?php

/* 
 * Store Crawler für Reddy Küchen (ID: 29073)
 */

class Crawler_Company_ReddyKuechen_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.reddy.de/';
        $searchUrl = $baseUrl . 'realisieren-vorteile-sichern/reddy-fachmaerkte-in-ihrer-naehe/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*list\s*=\s*(\[.+?\])#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $jStores = json_decode($storeListMatch[1]);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (preg_match('#passau#', $singleJStore->linkToMerchant)) {
                continue;
            }
            $sPage->open($singleJStore->linkToMerchant);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<[^>]*itemprop="([^"]+?)"[^>]*>(\s*<[^>]*>\s*)*\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: '. $singleJStore->linkToMerchant);
                continue;
            }
            
            $aInfos = array_combine($infoMatches[1], $infoMatches[3]);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $emailMatch)) {
                $eStore->setEmail($emailMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</ul#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setStreetAndStreetNumber($aInfos['streetAddress'])
                    ->setZipcode($aInfos['postalCode'])
                    ->setCity($aInfos['addressLocality'])
                    ->setPhoneNormalized($aInfos['telephone'])
                    ->setWebsite($singleJStore->linkToMerchant);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}