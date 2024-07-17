<?php

/**
 * Store Crawler für Erntebrot (ID: 71336)
 */
class Crawler_Company_Erntebrot_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.erntebrot.de/';
        $searchUrl = $baseUrl . 'Backshop.html';
        
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }
        
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<section class="backShopFinderResult[^"]*"[^>]*>[^<]*<a[^>]*href="([^"]+?)"#';
        if(!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleStoreLink) {            
            $eStore = new Marktjagd_Entity_Api_Store();
            $searchUrl = $baseUrl . $singleStoreLink;
            try {
                if (!$sPage->open($searchUrl)) {
                    $logger->log($companyId . ': unable to open store page. url: ' . $searchUrl, Zend_Log::ERR);
                    continue;
                }
            } catch (Exception $ex){
                continue;
            }
            
            $page = $sPage->getPage()->getResponseBody();
                        
            if (!preg_match('#<section[^>]*class="backShopDetail[^"]*"[^>]*>(.+?)</section>#', $page, $adrMatch)){
                $logger->log($companyId . ': unable to get address block. url: ' . $searchUrl, Zend_Log::WARN);
                continue;
            }

            if (!preg_match('#<li[^>]*class="ort"[^>]*>(.+?)<br>(.+?)</li>#', $adrMatch[1], $ortMatch)){
                $logger->log($companyId . ': unable to get city and street. url: ' . $searchUrl, Zend_Log::WARN);
                continue;
            }
            
            if (preg_match('#<h1>(.+?)</h1>#', $adrMatch[1], $titleMatch)){
                $eStore->setSubtitle($titleMatch[1]);
            }            

            if (preg_match('#<li[^>]*class="tel"[^>]*>(.+?)</li>#', $adrMatch[1], $telMatch)){
                $eStore->setPhone($sAddress->normalizePhoneNumber($telMatch[1]));                
            }
            
            if (preg_match('#<table[^>]*class="oefStart"[^>]*>(.+?)</li>#', $adrMatch[1], $oefMatch)){
                $eStore->setStoreHours($sTimes->generateMjOpenings($oefMatch[1]));                
            }
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $ortMatch[1]))                    
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $ortMatch[1]))
                    ->setCity($sAddress->extractAddressPart('city', $ortMatch[2]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $ortMatch[2]))                                                           
                    ->setWebsite(preg_replace('#\?PHP.*?$#', '', $eStore->getWebsite()));
                        
            $cStores->addElement($eStore);            
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
