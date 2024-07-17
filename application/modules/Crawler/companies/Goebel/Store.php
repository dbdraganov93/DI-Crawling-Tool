<?php

/**
 * Store Crawler für Göbel Getränke (ID: 71209)
 */
class Crawler_Company_Goebel_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.getraenke-goebel.de/';
        $searchUrl = $baseUrl . 'filialen.html/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sAddress = new Marktjagd_Service_Text_Address();
        
        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }
        
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="map-location"(.+?)</div>\s*</div>#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStore = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStoreMatch) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#id\:\s*([0-9]+?)\,.+?lng\:\s*(.+?)\,\s*lat\:\s*(.+?)\}#';
            if (!preg_match($pattern, $singleStoreMatch, $geoMatch)) {
                throw new Exception($companyId . ': unable to get store id.');
            }
            
            $pattern = '#<img[^>]*src="(.+?)"#';
            if (preg_match($pattern, $singleStoreMatch, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }
            
            $pattern = '#</strong>\s*<br[^>]*>(.+?)(<br[^>]*>Öffnungszeiten\:(.+?))?</p#';
            if (!preg_match($pattern, $singleStoreMatch, $detailMatch)) {
                throw new Exception($companyId . ': unable to get store details.' . $singleStoreMatch);
            }
            
            $aAddress = preg_split('#<br[^>]*>#', $detailMatch[1]);
            
            $eStore->setStoreNumber($geoMatch[1])
                    ->setLongitude($geoMatch[2])
                    ->setLatitude($geoMatch[3])
                    ->setStoreHours($sTimes->generateMjOpenings($detailMatch[2]))
                    ->setStreet($sAddress->extractAddressPart('street', $aAddress[0]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode($sAddress->extractAddressPart('zip', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($aAddress[2]));
            
            if (count($aAddress) == 5) {
                $eStore->setFax($sAddress->normalizePhoneNumber($aAddress[3]));
            }
            $cStore->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}