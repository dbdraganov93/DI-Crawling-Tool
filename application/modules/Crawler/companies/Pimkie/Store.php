<?php

/**
 * Storecrawler für Pimkie (ID: 28662)
 */
class Crawler_Company_Pimkie_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.pimkie.de/';
        $searchUrl = $baseUrl . 'on/demandware.store/Sites-pimkie-de-Site/de_DE/'
                . 'GeoJSON-NearestStores?lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
                . '&lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.1);
        
        $aStoreDetailsUrls = array();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson()->features;
            
            foreach ($jStores as $singleJStore) {
                $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>Mehr\s*Info#';
                if (!preg_match($pattern, $singleJStore->properties->rendering, $storeUrlMatch)) {
                    continue;
                }
                $aStoreDetailsUrls[] = $storeUrlMatch[1];
            }
        }
        
        $aStoreDetailsUrls = array_unique($aStoreDetailsUrls);
                
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreDetailsUrls as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4,5}\s+[A-Z][^<]+?)\s*<[^>]*>\s*Deutschland\s*#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->info($companyId . ': not a german store: ' . $singleStoreUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div[^>]*class="store-hours"[^>]*>(.+?)</ul#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized(preg_replace(array('#von#i', '#bis#i'), array('', '-'), $storeHoursMatch[1]));
            }
            
            $pattern = '#-(\d+)$#';
            if (preg_match($pattern, $singleStoreUrl, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }
            
            $pattern = '#Tel\s*:?\s*([^<]+?)</p#i';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], ucwords(strtolower($addressMatch[2])))
                    ->setWebsite($singleStoreUrl);
            
            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
