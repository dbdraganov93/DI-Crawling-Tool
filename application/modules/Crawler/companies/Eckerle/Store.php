<?php

/**
 * Store Crawler für Eckerle (ID: 71424)
 */
class Crawler_Company_Eckerle_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.eckerle.de/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*class="[^"]*filial_mini_hoverbox[^"]*"[^>]*href="([^"]+)"#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($storeMatches[1] as $singleStore) {
            if (preg_match('#onlineshop#', $singleStore)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();
        
            $this->_logger->info('open ' . $singleStore);
            $sPage->open($singleStore);
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore->setWebsite($singleStore);
            
            if (preg_match('#<section[^>]*class="[^"]*filial_description[^"]*"[^>]*>(.+?)</section>#', $page, $match)){
                $eStore->setText($match[1]);
            }
            
            if (preg_match('#<div[^>]*class="[^"]*filial_info[^"]*"[^>]*>(.+?)</div>#', $page, $infoMatch)){
                $addressLines = preg_split('#<br[^>]*>#', $infoMatch[1]);
                                
                foreach ($addressLines as $idx => $storeLine){                    
                    if (preg_match('#^[0-9]{5}\s#', trim($storeLine))){
                        $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $storeLine))
                                ->setCity($sAddress->extractAddressPart('city', $storeLine))
                                ->setStreet($sAddress->extractAddressPart('street', strip_tags($addressLines[$idx-1])))
                                ->setStreetNumber($sAddress->extractAddressPart('street_number', strip_tags($addressLines[$idx-1])));                                
                    }
                    
                    if (preg_match('#tel(.+$)#is', $storeLine, $match)){
                        $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
                    }
                    
                    if (preg_match('#fax(.+$)#is', $storeLine, $match)){
                        $eStore->setFax($sAddress->normalizePhoneNumber($match[1]));
                    }
                    
                    if (preg_match('#mailto:([^"]+)"#', $storeLine, $match)){
                        $eStore->setEmail($match[1]);
                    }
                }
                
                if (preg_match('#zeiten(.+$)#', $infoMatch[1], $match)){
                    $eStore->setStoreHours($sTimes->generateMjOpenings($match[1]));
                }
            }
            
            if (preg_match('#<p>\s*<b>\s*Parkmöglichkeiten\s*</b>\s*</p>\s*<p>(.+?)</p>#', $page, $match)){
                $eStore->setParking($match[1]);
            }
                        
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}