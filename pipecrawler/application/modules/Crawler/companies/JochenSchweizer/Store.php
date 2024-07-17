<?php

/**
 * Store Crawler für Jochen Schweizer (ID: 71566)
 */

class Crawler_Company_JochenSchweizer_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.jochen-schweizer.de';
        $searchUrl = $baseUrl . '/shops/uebersicht,default,pg.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
                
        if (!preg_match_all('#<a[^>]*href="(/shops/[^\/]+?\/[^"]+)">#', $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            Zend_Debug::dump($baseUrl . $singleStore);
            $sPage->open($baseUrl . $singleStore);
            $page = $sPage->getPage()->getResponseBody();
                        
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setWebsite($baseUrl . $singleStore);
            if (preg_match_all('#style="background-image:url\(([^\(]+)\)#', $page, $match)){
                $eStore->setImage(implode(',', $match[1]));
                
                if ($eStore->getImage() == 'null'){
                    $eStore->setImage('');
                }
            }
            
            if (preg_match('#<div[^>]*class="js-stores-details-addr-open-inner"[^>]*>\s*<span[^>]*>.+?</span>\s*<p[^>]*>(.+?)</p>\s*</div>#', $page, $match)){
                $addressLines = preg_split('#<br[^>]*>#', $match[1]);
                $eStore->setSubtitle(trim($addressLines[0]))
                        ->setStreet($sAddress->extractAddressPart('street', $addressLines[1]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[1]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[2]))
                        ->setCity($sAddress->extractAddressPart('city', $addressLines[2]));
            }
            
            if (preg_match('#ffnungszeiten</span>(.+?)</div>#', $page, $match)){                
                $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace('#von#', '', $match[1])));
            }
            
            $cStores->addElement($eStore);
        } 
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}