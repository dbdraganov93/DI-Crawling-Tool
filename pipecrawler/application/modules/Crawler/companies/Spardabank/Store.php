<?php

/**
 * Store Crawler für Spardabank (ID: )
 */

class Crawler_Company_Spardabank_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.sparda-filialfinder.de/';
        $searchUrl = $baseUrl . 'Partners/SpardaBank/Start.aspx?BC=SPADxBER&SingleSlot='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $oPage = $sPage->getPage();
        $oPage->setUseCookies(true);
        $sPage->setPage($oPage);
        
        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode');
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#SessionGuid:\s*"([^"]+?)"#';
            if (!preg_match($pattern, $page, $idMatch)) {
//                throw new Exception ($companyId . ': unable to get session id.');
                continue;
            }
            
            $pattern = '#Map\.PoiDataHandler.+?(\[.+?\])#';
            if (!preg_match_all($pattern, $page, $storeDataMatches)) {
                $this->_logger->info($companyId . ': unable to get any store data.');
            }
            
            $pattern = '#e:\s*\'([^\']+?)\'#';
            if (!preg_match_all($pattern, $storeDataMatches[1][1], $storeIdMatches)) {
                $this->_logger->info($companyId . ': unable to get any store ids.');
            }
            
            foreach ($storeIdMatches[1] as $singleStoreId) {
                $detailUrl = $baseUrl . 'FilialFinder/Html/PoiJSService.aspx?SessionGuid='
                        . $idMatch[1] . '&JSLayerID=Basis&DataSetIds='
                        . $singleStoreId . '&NewSession=No&JSPoiDetails=3&PoiDetailMemoFields=true&'
                        . 'PoiDetailObjectListItems=1';
                $sPage->open($detailUrl);
                $page = $sPage->getPage()->getResponseBody();
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#address1"[^>]*>\s*(.+?)\s*<.+?address2"[^>]*>\s*(.+?)\s*<#';
                if (!preg_match($pattern, $page, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $detailUrl);
                    continue;
                }
                
                $pattern = '#ffnungszeiten(.+?)</tbody#is';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
                }
                
                $pattern = '#ServicePropertiesText"[^>]*>\s*(.+?)\s*<#';
                if (preg_match_all($pattern, $page, $serviceMatches)) {
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
                
                $pattern = '#telefon.+?>\s*(.+?)\s*<#i';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                }
                
                $pattern = '#fax.+?>\s*(.+?)\s*<#i';
                if (preg_match($pattern, $page, $faxMatch)) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
                }
                
                $eStore->setStoreNumber($singleStoreId)
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $addressMatch[1])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatch[1])))
                        ->setCity($sAddress->extractAddressPart('city', $addressMatch[2]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[2]));
                
                $cStores->addElement($eStore, true);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}