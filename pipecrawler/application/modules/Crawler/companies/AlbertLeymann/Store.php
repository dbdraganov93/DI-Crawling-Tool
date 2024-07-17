<?php

/*
 * Store Crawler für Albert Leymann (ID: 71172)
 */

class Crawler_Company_AlbertLeymann_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.leymann-baustoffe.de/';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
               
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();                                         
        
        if (!preg_match_all('#<li[^>]*>\s*<a[^>]*href="([^"]+standort/detail/[^"]+)"[^>]*>#', $page, $storeLinkMatches)) {
            throw new Exception('unable to get any stores.');
        }           
        
        foreach ($storeLinkMatches[1] as $singleStoreLink) {            
            $sPage->open($singleStoreLink);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<address[^>]*>(.+?)</address>#s';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreLink);
                continue;
            }
            
            $aInfos = preg_split('#(\s*<[^>]*>\s*)+#', $addressMatch[1]);
                                   
            $eStore = new Marktjagd_Entity_Api_Store();
            
            for ($i = 0; $i < count($aInfos); $i++) {
                if (preg_match('#^\d{5}#', $aInfos[$i])) {
                    $eStore->setStreetAndStreetNumber($aInfos[$i - 1])
                            ->setZipcodeAndCity($aInfos[$i]);
                    continue;                    
                }
                
                if (preg_match('#fon#', $aInfos[$i])) {
                    $eStore->setPhoneNormalized($aInfos[$i]);
                    continue;
                }
                
                if (preg_match('#fax#', $aInfos[$i])) {
                    $eStore->setFaxNormalized($aInfos[$i]);
                    continue;
                }
                
                if (preg_match('#\@#', $aInfos[$i])) {
                    $eStore->setEmail($aInfos[$i]);
                    continue;
                }
            }
            
            $pattern = '#ffnungszeiten(.+?)</p#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#<h[^>]*>\s*Unser\s*Sortiment(.+?)</ul#s';
            if (preg_match($pattern, $page, $sectionListMatch)) {
                $pattern = '#<a[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $sectionListMatch[1], $sectionMatches)) {
                    $eStore->setSection(implode(', ', $sectionMatches[1]));
                }
            }
            
            $pattern = '#<h[^>]*>\s*Unser\s*Service(.+?)</ul#s';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<li[^>]*>\s*(.+?)\s*</li>#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $eStore->setService(strip_tags(implode(', ', $serviceMatches[1])));
                }
            }
            
            $eStore->setWebsite($singleStoreLink);
                        
            $cStores->addElement($eStore);
        }                     
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
