<?php

/* 
 * Store Crawler für Pleines (ID: 71072)
 */

class Crawler_Company_Pleines_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.pleines.de/';
        $searchUrl = $baseUrl . 'niederlassungen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#img-responsive"\s*alt="(Pl?eines.+?)\s*<small#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#informationblock[^>]*>\s*<div[^>]*>\s*(.+?)<br[^>]*>\s*<a#';
            if (!preg_match($pattern, $singleStore, $storeInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $singleStore);
                continue;
            }
            
            $aInfos = preg_split('#(\s*<br[^>]*>\s*)+#', $storeInfoMatch[1]);
            
            $pattern = '#ffnungszeiten(.+?)</div#i';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $pattern = '#<a[^>]*href="javascript\:linkTo\_UnCryptMailto[^>]*>(.+?)<#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#src="(typo3temp[^"]+?)"#';
            if (preg_match($pattern, $singleStore, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }
            
            
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aInfos[0])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aInfos[0])))
                    ->setCity($sAddress->extractAddressPart('city', $aInfos[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aInfos[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($aInfos[2]))
                    ->setFax($sAddress->normalizePhoneNumber($aInfos[3]))
                    ->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}