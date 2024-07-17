<?php

/*
 * Store Crawler für Kaufhaus Stolz (ID: 68872)
 */

class Crawler_Company_KaufhausStolz_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://unternehmen.kaufhaus-stolz.com/';
        $searchUrl = $baseUrl . 'filialen';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#class="filial_description_mehr_link"[^>]*>\s*<a\s*href="([^"]+?)"#s';
        if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any store links.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($storeLinkMatches[1] as $singleStoreLink) {
            $sPage->open($singleStoreLink);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div\s*id="filial_([0-9]+?)"[^>]*>(.+?)<footer#';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $singleStoreLink);
            }

            $pattern = '#class="[^"]+?kontakt_daten"[^>]*>\s*<p[^>]*>(.+?)<br[^>]*>\s*</p#s';
            if (!preg_match($pattern, $storeInfoMatch[2], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeInfoMatch[1]);
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);
            
            $pattern = '#parkmoeglichkeiten_inner"[^>]*>\s*<p[^>]*>\s*(.+?)\s*<#';
            if (preg_match($pattern, $storeInfoMatch[2], $parkingMatch)) {
                $eStore->setParking($parkingMatch[1]);
            }
            
            $pattern = '#fon:?\s*(.+?)\s*<.+?fax:?\s*(.+?)\s*<.+?mailto:([^"]+?)"#';
            if (preg_match($pattern, $storeInfoMatch[2], $contactMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($contactMatch[1]))
                        ->setFax($sAddress->normalizePhoneNumber($contactMatch[2]))
                        ->setEmail($contactMatch[3]);
            }
            
            $pattern = '#</h2>\s*</div>\s*<div[^>]*oeffnungszeiten_inner\s*wochenansicht_smartphone[^>]*>(.+?)<h2[^>]*>#';
            if (preg_match($pattern, $storeInfoMatch[2], $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
                        
            $eStore->setStoreNumber($storeInfoMatch[1])
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress)-2])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress)-2])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress)-1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[count($aAddress)-1]))
                    ->setWebsite($singleStoreLink);
                       
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
