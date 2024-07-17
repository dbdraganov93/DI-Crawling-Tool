<?php

/**
 * Store Crawler für Gravis (ID: 29034)
 */
class Crawler_Company_Gravis_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.gravis.de/';
        $searchUrl = $baseUrl . 'filialen/';        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();       
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<li[^>]*class="item[^"]*"[^>]*>\s*<a[^>]*href="(filialen[^"]+)">#';
        if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception ($companyId . ': unable to get any stores.');
        }
                
        $storeList = array();
                
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleStoreLink) {
            $this->_logger->info('open ' . $baseUrl . $singleStoreLink);
            $sPage->open($baseUrl . $singleStoreLink);
            $page = $sPage->getPage()->getResponseBody();
            
            if (!preg_match_all('#<a[^>]*href="(' . $singleStoreLink . '[^"]+)"[^>]*class="h2"#', $page, $detailLinkMatches)){
                $this->_logger->info('cannot find detail store link use ' . $singleStoreLink);
                $storeList[] = $baseUrl . $singleStoreLink;
            }
            
            foreach ($detailLinkMatches[1] as $detailLink){
                $this->_logger->info('find detail store link use ' . $detailLink);
                $storeList[] = $baseUrl . $detailLink;
            }            
        }
                
        foreach ($storeList as $storeUrl){
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();
                        
            $eStore = new Marktjagd_Entity_Api_Store();                 
                        
            if (preg_match('#<div[^>]*>[^<]*zeiten[^<]*</div>(.+?)</div>#', $page, $hoursMatch)){
                $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
            }
            
            if (preg_match('#<div[^>]*>\s*<h2>(.+?)</h2>\s*<div[^>]*>\s*<p>(.+?)</p>\s*<p>(.+?)</p>#', $page, $addressMatch)){
                $addressLines = preg_split('#<br[^>]*>#', $addressMatch[2]);

                $eStore->setStreet($sAddress->extractAddressPart('street', $addressLines[0]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[0]))
                        ->setCity($sAddress->extractAddressPart('city', $addressLines[1]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[1]))
                        ->setWebsite($storeUrl);
                
                if (preg_match('#<a[^>]*>(.+?\@.+?)</a>#', $addressMatch[3], $mailMatch)){
                    $eStore->setEmail(trim($mailMatch[1]));
                }
                
                if (preg_match('#fax:\s*([^<]+?)\s*<#i', $page, $faxMatch)){
                    $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
                }
                
            }                                                                    
            
            $cStores->addElement($eStore, TRUE);            
        }                
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}