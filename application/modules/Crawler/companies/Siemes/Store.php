<?php

/* 
 * Store Crawler für Siemes Schuhcenter (ID: 29144)
 */

class Crawler_Company_Siemes_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.schuhcenter.de/';
        $searchUrl = $baseUrl . 'filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<li[^>]*>\s*<a[^>]*href="(https:\/\/www\.schuhcenter\.de\/filialen-in-[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeCityMatches)) {
            throw new Exception($companyId . ': unable to get any store city urls.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeCityMatches[1] as $singleCity) {
            $sPage->open($singleCity);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<article[^>]*class="store_finder_block"[^>]*>(.+?)</article>#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores from: ' . $singleCity);
                continue;
            }
            
            foreach ($storeMatches[1] as $singleStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#<img[^>]*src="([^"]+?)"#';
                if (!preg_match($pattern, $singleStore, $imageMatch)) {
                    $this->_logger->info($companyId . ': unable to get image: ' . $singleStore);
                }
                
                $pattern = '#itemprop="([^"]+?)"[^>]*>([^<]+?)<#';
                if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                    $this->_logger->err($companyId . ': unable to get any store infos: ' . $singleStore);
                    continue;
                }
                
                $aInfos = array_combine($infoMatches[1], $infoMatches[2]);
                
                $pattern = '#itemprop="url"[^>]*href="([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $urlMatch)) {
                    $eStore->setWebsite($urlMatch[1]);
                }
                
                $pattern = '#ffnungszeiten(.+?)</div#s';
                if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }
                
                $pattern = '#<img[^>]*src="http:\/\/www\.schuhcenter\.de\/out\/schuhcenter\/img\/parking_icon\.png"[^>]*>\s*</div>\s*'
                    .'<div[^>]*class="rt_info">\s*<span[^>]*>([^<]+?)</span>#';
                if (preg_match($pattern, $singleStore, $parkingMatch)) {
                    $eStore->setParking($parkingMatch[1]);
                }
                
                $pattern = '#<img[^>]*src="http:\/\/www\.schuhcenter\.de\/out\/schuhcenter\/img\/retail_space_icon\.png"[^>]*>\s*</div>\s*'
                    .'<div[^>]*class="rt_info">\s*<span[^>]*>([^<]+?)</span>#';
                if (preg_match($pattern, $singleStore, $serviceMatch)) {
                    $eStore->setService($serviceMatch[1]);
                }
                
                $eStore->setImage($imageMatch[1])
                        ->setStreetAndStreetNumber($aInfos['streetAddress'])
                        ->setZipcode($aInfos['postalCode'])
                        ->setCity($aInfos['addressLocality'])
                        ->setPhoneNormalized($aInfos['telephone']);
                
                if (!preg_match('#^http#', $eStore->getImage())) {
                    $eStore->setImage($baseUrl . preg_replace('#^\/#', '', $imageMatch[1]));
                }
                
                if (!preg_match('#^\d#', $eStore->getZipcode())) {
                    continue;
                }
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}