<?php

/**
 * Store Crawler für Walbusch (ID: 71790)
 */
class Crawler_Company_Walbusch_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.walbusch.de';
        $searchUrl = $baseUrl . '/store-finder/search?q=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aLinks = $sGen->generateUrl($searchUrl, 'zip', 100);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        $storeLinks = array();
        
        foreach ($aLinks as $singleLink) {
            $this->_logger->info('open ' . $singleLink);
            if (!$sPage->open($singleLink)) {
                throw new Exception ($companyId . ': unable to open store list page. url: ' . $singleLink);
            }
                        
            $json = $sPage->getPage()->getResponseAsJson();
           
            foreach ($json->stores as $store){
                $storeLinks[] = $store->contentPageUrl;
            }
        }
        
        $storeLinks = array_unique($storeLinks);
        
        foreach ($storeLinks as $storeLink){
            $this->_logger->info('open ' . $baseUrl . $storeLink);
            try {
                $sPage->open($baseUrl . $storeLink);
            } catch (Exception $ex){
                continue;
            }
            
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            if (!preg_match('#<section[^>]*class="content-middle"[^>]*>(.+?)</section>\s*</section>#', $page, $sectionMatch)){
                $this->_logger->err('no store section on ' . $storeLink);
                continue;
            }      
            
            $eStore->setWebsite($baseUrl . $storeLink);
            
            if (preg_match('#<h3>Adresse</h3>\s*<p>(.+?)</p>#is', $sectionMatch[1], $match)){
                $addressLines = preg_split('#<br[^>]*>#', $match[1]);

                foreach($addressLines as $idx => $addressLine){
                    if (preg_match('#^[0-9]{5}\s#', trim($addressLine))){
                        $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $addressLine))
                                ->setCity($sAddress->extractAddressPart('city', $addressLine))
                                ->setStreet($sAddress->extractAddressPart('street', $addressLines[$idx-1]))
                                ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[$idx-1]));
                        
                        if ($idx > 1){
                            $eStore->setSubtitle($addressLines[0]);
                        }                        
                    }
                }
            }
            
            if (preg_match('#<h3[^>]*>Öffnungszeiten</h3>(.+?)<p>#is', $sectionMatch[1], $match)){
                $eStore->setStoreHours($sTimes->generateMjOpenings($match[1]));
            }               
            
            if (preg_match('#<h3>Parken</h3>\s*<p>(.+?)</p>#is', $sectionMatch[1], $match)){
                $eStore->setParking($match[1]);
            }
            
            if (preg_match('#href="mailto:([^"]+)"#is', $sectionMatch[1], $match)){
                $eStore->setEmail($match[1]);
            }
            
            if (preg_match('#>tel[^<]*</div>\s*<div[^>]*>([^<]+)</div>#is', $sectionMatch[1], $match)){
                $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
            }
            
            if (preg_match('#>fax[^<]*</div>\s*<div[^>]*>([^<]+)</div>#is', $sectionMatch[1], $match)){
                $eStore->setFax($sAddress->normalizePhoneNumber($match[1]));
            }
            
            $cStores->addElement($eStore);            
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}