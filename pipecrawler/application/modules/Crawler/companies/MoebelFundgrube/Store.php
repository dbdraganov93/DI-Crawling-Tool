<?php

/* 
 * Store Crawler für Möbel Fundgrube (ID: 71735)
 */

class Crawler_Company_MoebelFundgrube_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.moebel-fundgrube.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a\s*href="([^"]+?)"[^>]*>\s*Möbel#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<strong[^>]*>\s*(Filiale|Zentrallager)(.+?</tr)#s';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $storeDetailUrl);
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>([0-9]{5}.+?)</font#';
            if (!preg_match($pattern, $storeInfoMatch[2], $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeAddressMatch[1]);
            
            $pattern = '#<p[^>]*>\s*(.+?)\s*</font#';
            if (preg_match($pattern, $storeInfoMatch[2], $storeTextMatch)) {
                $eStore->setText(preg_replace('#-\s+#', '', trim(strip_tags($storeTextMatch[1]))));
            }
            
            $pattern = '#<img\s*src="([^"]+?)"#';
            if (preg_match($pattern, $storeInfoMatch[2], $storeImageMatch)) {
                $eStore->setImage($baseUrl . $storeImageMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</font#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[1])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[1])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[0]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[0]))
                    ->setPhone($sAddress->normalizePhoneNumber(end($aAddress)));
            
            if (count($aAddress) == 4) {
                $eStore->setSubtitle(preg_replace('#\((.+?)\)#', '$1', $aAddress[2]));
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}