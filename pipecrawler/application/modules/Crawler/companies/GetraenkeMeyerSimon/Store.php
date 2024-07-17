<?php

/**
 * Store Crawler für Getränke meyer & simon (ID: 71720)
 */
class Crawler_Company_GetraenkeMeyerSimon_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.meyer-simon.de';
        $storeUrl = $baseUrl . '/site/iaast/';
        $searchUrl = $storeUrl . 'iaast.html';        
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);        
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<area[^>]*href="([^"]+)"#s';
        if (!preg_match_all($pattern, $page, $subLinks)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
                
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($subLinks[1] as $subLink){
            $sPage->open($storeUrl . $subLink);
            $page = $sPage->getPage()->getResponseBody();
            
            
            $pattern = '#<img[^>]*src="([^"]+)"[^>]*>\s*<br>\s*<br>\s*<span[^>]*class="hauptblaufettschatten"[^>]*>(.+?)\s*</span>.+?<span[^>]*class="haupt_weiss_fett"[^>]*>(.+?)<br>(.+?)<br>(.+?)</span>#';
            if (!preg_match_all($pattern, $page, $addressMatch)) {
                throw new Exception($companyId . ': unable to get any stores.');
            }
            
            foreach ($addressMatch[0] as $idx => $addressInfo){
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setSubtitle($addressMatch[2][$idx])
                        ->setStreet($sAddress->extractAddressPart('street', $addressMatch[3][$idx]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressMatch[3][$idx]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[4][$idx]))
                        ->setCity($sAddress->extractAddressPart('city', $addressMatch[4][$idx]))
                        ->setPhone($sAddress->normalizePhoneNumber($addressMatch[5][$idx]));
                
        
                $eStore->setLogo($storeUrl . $addressMatch[1][$idx]);
                                
                $cStores->addElement($eStore);                       
            }
        }        
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}