<?php

/* 
 * Store Crawler für Harry's Fliesenmarkt (ID: 71691)
 */

class Crawler_Company_Harrys_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.harrys-fliesenmarkt.net/';
        $searchUrl = $baseUrl . 'alle-niederlassungen/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($baseUrlUrl);
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
                
        $pattern = '#<li[^>]*>\s*<a[^>]*href="(alle-niederlassungen[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any stores from list.');
        }                                                      
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $storeDetailUrl) {
            $this->_logger->info('open '. $storeDetailUrl);
            $storeUrl = $baseUrl . $storeDetailUrl;
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#kontaktdaten(.+?)<h3#si';
            if (!preg_match($pattern, $page, $infoMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $storeUrl);
                continue;
            }
            
            $pattern = '#<p[^>]*>\s*(.+?)\s*</p#';
            if (!preg_match_all($pattern, $infoMatch[1], $storeDetailMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos from list: ' . $storeUrl);
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeDetailMatches[1][0]);
            $aContact = preg_split('#\s*<br[^>]*>\s*#', $storeDetailMatches[1][1]);
            
            $pattern = '#ffnungszeiten(.+?)</p#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace(array('#bis#', '#von#'), array('-', ''), $storeHoursMatch[1])));
            }
            
            $pattern = '#steckbrief.+?<p[^>]*>\s*(.+?)(</p|<br[^>]*>\s*<br[^>]*>)#i';
            if (preg_match($pattern, $page, $storeTextMatch)) {
                $eStore->setText($storeTextMatch[1]);
            }
            
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setEmail($storeDetailMatches[1][2])
                    ->setPhone($sAddress->normalizePhoneNumber($aContact[0]))
                    ->setFax($sAddress->normalizePhoneNumber($aContact[1]))
                    ->setWebsite($storeUrl)
                    ->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}