<?php

/**
 * Store Crawler für Mister*Lady Jeans (ID: 67729)
 */
class Crawler_Company_MisterLady_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.mister-lady.com/';
        $searchUrl = $baseUrl . 'Storefinder/search';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $aParams = array(
            'distance' => '100',
            'input' => '',
            'catFilter' => '',
            'byname' => ''
            );
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $oPage->setUseCookies(TRUE);
        $sPage->setPage($oPage);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 5; $i <= 15; $i += 0.2) {
            for ($j = 45; $j <= 55; $j += 0.2) {
                $aParams['lng'] = $i;
                $aParams['lat'] = $j;
                
                $sPage->open($searchUrl, $aParams);
                $page = $sPage->getPage()->getResponseBody();
                
                $pattern = '#<div[^>]*id="shopdetail(.+?)\s*</script#';
                if (!preg_match_all($pattern, $page, $storeMatches)) {
                    $this->_logger->info($companyId . ': no stores for lat ' . $j . ', lng ' . $i);
                    continue;
                }
                
                foreach ($storeMatches[1] as $singleStore) {
                    $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
                    if (!preg_match($pattern, $singleStore, $addressMatch)) {
                        $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                        continue;
                    }
                    
                    $eStore = new Marktjagd_Entity_Api_Store();
                    
                    $pattern = '#class="telnummerlink"[^>]*>\s*([^<]+?)\s*<#';
                    if (preg_match($pattern, $singleStore, $phoneMatch)) {
                        $eStore->setPhoneNormalized($phoneMatch[1]);
                    }
                    
                    $pattern = '#class="netiBusinessHours\s*label_weekday[^>]*>(.+?)<br#';
                    if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                        $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                    }
                    
                    $eStore->setAddress($addressMatch[1], $addressMatch[2]);
                                        
                    $cStores->addElement($eStore);
                }
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}