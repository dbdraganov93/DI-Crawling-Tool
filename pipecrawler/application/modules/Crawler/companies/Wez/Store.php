<?php

/* 
 * Store Crawler für WEZ (ID: 67790)
 */

class Crawler_Company_Wez_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://wez.de/';
        $searchUrl = $baseUrl . 'lebensmittel-punkt/unsere-markte/filialfinder/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#alle\s*märkte(.+?)</ul#si';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<a\s*class="weiter"\s*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#adresse\s*und\s*kontakt\:?(\s*<[^>]*>\s*)*(.+?)</p#si';
            if (!preg_match($pattern, $page, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleUrl);
            }
            
            $aAddress = preg_split('#\s*<[^>]*>\s*#', $storeAddressMatch[2]);
            
            $pattern = '#ffnungszeiten(.+?)</p#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $pattern = '#<div[^>]*class="col\s*width_66">\s*<div class="box">\s*<img\s*src="([^"]+?jpg)"#';
            if (preg_match($pattern, $page, $storeImageMatch)) {
                $eStore->setImage($storeImageMatch[1]);
            }
            
            $pattern = '#ansprechpartner([a-z]{2})?(\s*<[^>]*>\s*)*<strong[^>]*>\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $page, $storeTextMatch)) {
                $eStore->setText('Ansprechpartner' . $storeTextMatch[1] . ' in der Filiale: ' . $storeTextMatch[3]);
            }
            
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber(preg_replace('#(\s+und.+)#', '', $aAddress[2])))
                    ->setSubtitle('Partner der EDEKA - Gruppe')
                    ->setWebsite($singleUrl);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}