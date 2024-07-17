<?php

/**
 * Store Crawler für Elli Markt (ID: 71349)
 */
class Crawler_Company_Elli_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.elli-markt.de/';
        $searchUrl = $baseUrl . 'standorte.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#href="(standorte\/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any store links.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleStoreLink) {
            $searchUrl = $baseUrl . $singleStoreLink;
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*id="main_content"[^>]*>(.+?)</h3#';
            if (!preg_match($pattern, $page, $storeDetailMatch)) {
                $this->_logger->err($companyId . ': unable to get store details for url: ' . $searchUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div[^>]*id="(c[0-9]+)"#';
            if (!preg_match($pattern, $storeDetailMatch[1], $storeNoMatch)) {
                $this->_logger->err($companyId . ': unable to get store number for url: ' . $searchUrl);
                continue;
            }
            
            $pattern = '#(</p>\s*<p>|</b>)(.+?)</a>#s';
            if (!preg_match($pattern, $storeDetailMatch[1], $storeInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos for url: ' . $searchUrl);
                continue;
            }
            
            $pattern = '#Öffnungszeiten:\s*(.+?)<#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace(array('#\s*von\s*#', '#\s*bis\s*#'), array('', '-'), $storeHoursMatch[1])));
            }
            
            $aInfos = preg_split('#\s*(<br[^>]*>|</p>\s*<p[^>]*>)\s*#', $storeInfoMatch[2]);
            $strMail = preg_replace('#E-Mail:\s*#', '', strip_tags($aInfos[count($aInfos)-1]));
            
            $eStore->setText('Marktleitung: ' . $aInfos[0])
                    ->setStreet($sAddress->extractAddressPart('street', $aInfos[1]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aInfos[1]))
                    ->setCity($sAddress->extractAddressPart('city', $aInfos[2]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aInfos[2]))
                    ->setPhone($sAddress->normalizePhoneNumber($aInfos[count($aInfos)-3]))
                    ->setFax($sAddress->normalizePhoneNumber($aInfos[count($aInfos)-2]))
                    ->setEmail($strMail);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}