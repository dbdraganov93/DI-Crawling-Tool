<?php

/* 
 * Store Crawler für Nowebau (ID: 29125)
 */

class Crawler_Company_Nowebau_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.nowebau.de/';
        $searchUrl = $baseUrl . 'standorte/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#addBubble\s*\(\s*"map1146",\s*2\s*,(.+?addMarker.+?\]),#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception ($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div[^>]*>(.+?)<\\\/div>#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $singleStore);
                continue;
            }
            
            $pattern = '#(<\\\/p>)?(.+?)<br[^>]*>\s*<br[^>]*>#';
            if (!preg_match($pattern, $infoMatches[1][1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $infoMatches[1][0]);
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[2]);
            
            foreach ($aAddress as &$singleValue) {
                $singleValue = preg_replace('#\\\\u([a-f0-9]{4})#e', 'iconv(\'UCS-4LE\',\'UTF-8\',pack(\'V\', hexdec(\'U$1\')))', strip_tags($singleValue));
            }
            
            $pattern = '#<a\s*href=\\\"(.+?)\\\"#';
            if (preg_match($pattern, $infoMatches[1][1], $websiteMatch)) {
                $eStore->setWebsite(preg_replace('#\\\#', '', $websiteMatch[1]));
            }
            
            $pattern = '#>([^<]+?\(at\)[^<]+?)<#';
            if (preg_match($pattern, $infoMatches[1][2], $mailMatch)) {
                $eStore->setEmail(preg_replace('#\(at\)#', '@', $mailMatch[1]));
            }
            
            $eStore->setStreet(preg_replace('#\\\#', '', $sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0]))))
                    ->setStreetNumber(preg_replace('#\\\#', '', $sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0]))))
                    ->setCity(preg_replace('#\\\#', '', $sAddress->extractAddressPart('city', $aAddress[1])))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($aAddress[2]))
                    ->setStoreHours($sTimes->generateMjOpenings($infoMatches[1][3]));
            
            if (!preg_match('#nowebau#', $infoMatches[1][0])) {
                $eStore->setSubtitle(preg_replace(array('#\\\\u([a-f0-9]{4})#e', '#\\\#'), array('iconv(\'UCS-4LE\',\'UTF-8\',pack(\'V\', hexdec(\'U$1\')))', ''), $infoMatches[1][0]));
            }
            
            $eStore->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}