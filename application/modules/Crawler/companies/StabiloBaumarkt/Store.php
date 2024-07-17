<?php

/**
 * Storecrawler für Stabilo Baumarkt (ID: 69917)
 */
class Crawler_Company_StabiloBaumarkt_Store extends Crawler_Generic_Company{
    
    public function crawl($companyId) {
        
        $baseUrl = 'https://www.stabilo-fachmarkt.de/';
        $searchUrl = $baseUrl . 'maerkte/';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();        
        $sAddress = new Marktjagd_Service_Text_Address();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $sPage->open($searchUrl);            
        $page = $sPage->getPage()->getResponseBody();        
                
        $pattern = '#<a[^>]*href="[^"]*\/(maerkte/[^"]+)"#si';
        if(!preg_match_all($pattern, $page, $linksMatches)) {
            $logger->log('unable to get stores of company with id ' . $companyId, Zend_Log::ERR);
        }
        
        foreach($linksMatches[1] as $storeLink){
            $eStore = new Marktjagd_Entity_Api_Store();
                        
            $this->_logger->info('open ' . $baseUrl . $storeLink);
            $sPage->open($baseUrl . $storeLink);            
            $page = $sPage->getPage()->getResponseBody();            
            
            if (preg_match('#<div[^>]*class="main_column"[^>]*>\s*<p>\s*<strong>.+?</strong>\s*<br[^>]*>(.+?)</p>#is', $page, $addressMatch)){
                $addressLines = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);
                
                foreach ($addressLines as $idx => $line){
                    if (preg_match('#^[0-9]{5}\s#', $line) && !strlen($eStore->getZipcode())){
                        $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $line))
                                ->setCity($sAddress->extractAddressPart('city', $line))
                                ->setStreet($sAddress->extractAddressPart('street', $addressLines[$idx-1]))
                                ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $addressLines[$idx-1]));                                
                    }
                    
                    if (preg_match('#tel#is', $line) && !strlen($eStore->getPhone())){
                        $eStore->setPhone($sAddress->normalizePhoneNumber($line));                                
                    }
                    
                    if (preg_match('#fax#is', $line) && !strlen($eStore->getFax())){
                        $eStore->setFax($sAddress->normalizePhoneNumber($line));                                
                    }
                    
                    if (preg_match('#([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4})#is', $line, $mailMatch) && !strlen($eStore->getEmail())){
                        $eStore->setEmail($mailMatch[1]);                                
                    }
                    
                }                
            }

            if (preg_match('#ffnungszeiten(.+?)</p#', $page, $hoursMatch)){
                $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
            }

            $cStores->addElement($eStore, true);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        $crawlerResponse = new Crawler_Generic_Response();
        $crawlerResponse->generateResponseByFileName($fileName);

        return $crawlerResponse;
    }
}