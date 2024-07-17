<?php

/* 
 * Store Crawler für ZG Raiffeisen (ID: 71713)
 */

class Crawler_Company_ZGRaiffeisen_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.zg-raiffeisen.de/';
        $searchUrl = $baseUrl . 'sonstiges/standortfinder/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*href="http:\/\/www\.zg-raiffeisen\.de\/index\.php\?id=([0-9]+?)"[^>]*>Details#';
        if (!preg_match_all($pattern, $page, $storeNumberMatches)) {
            throw new Exception($companyId . ': unable to get any store links.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeNumberMatches[1] as $singleStoreNumber) {
            $storeDetailUrl = 'http://www.zg-raiffeisen.de/index.php?id=' . $singleStoreNumber;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>([^>]+?)<br[^>]*>\s*([0-9]{5})\s*([^<\']+?)<#i';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->info($companyId . ' not a german store: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#tel\.?:?\s*([0-9\/-]+?)<#i';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#fax\.?:?\s*([0-9\/-]+?)<#i';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }
            
            $pattern = '#href="mailto:([^"]+?)"#i';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)<a#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $pattern = '#<img[^>]*src="(uploads\/tx_templavoila\/[^"]+?)"#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }
            
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $addressMatch[1])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatch[1])))
                    ->setZipcode($addressMatch[2])
                    ->setCity($addressMatch[3])
                    ->setStoreNumber($singleStoreNumber);
            
            if (preg_match('#\(F\)#', $eStore->getCity())) {
                continue;
            }
            
            $cStores->addElement($eStore, true);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}