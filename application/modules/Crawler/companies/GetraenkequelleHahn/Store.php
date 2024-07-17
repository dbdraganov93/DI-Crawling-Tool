<?php

/* 
 * Store Crawler für Getränkequelle Hahn (ID: 69547)
 */

class Crawler_Company_GetraenkequelleHahn_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.hahn-getraenke-union.de/';
        $overViewUrl = $baseUrl . 'index.php/unsere-maerkte.html';                
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        if(!$sPage->open($overViewUrl)){
            throw new Exception('Could not open url:' . $overViewUrl);
        }
        
        $page = $sPage->getPage()->getResponseBody();
        
        if (!preg_match_all('#marker\s*=\s*getMarker\((.+?)\);#', $page, $markerMatch)){
            throw new Exception('cannot get icon/store markers from ' . $overViewUrl);
        }
        
        foreach($markerMatch[1] as $singleStore){
            $eStore = new Marktjagd_Entity_Api_Store();

            if (!preg_match('#[^\']*\'([^\']+)\'.+?LatLng\(([^\,]+)\,([^\,]+)\)\s*\,\s*\'([^\']+)\'#', $singleStore, $geoMatch)){
                $this->_logger->warn('cannot match store info from marker ' . $singleStore);
                continue;
            }                        
            
            $eStore->setWebsite($baseUrl . $geoMatch[1])
                        ->setLatitude($geoMatch[2])
                        ->setLongitude($geoMatch[3]);
            
            $infoLines = preg_split('#<p[^>]*>#', $geoMatch[4]);            
            $addressLines = preg_split('#<br[^>]*>#', $infoLines[2]);
            
            $eStore->setSubtitle(trim(strip_tags($addressLines[0])))
                    ->setStreet($sAddress->extractAddressPart('street', trim(strip_tags($addressLines[1]))))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', trim(strip_tags($addressLines[1]))))
                    ->setCity($sAddress->extractAddressPart('city', trim(strip_tags($addressLines[2]))))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', trim(strip_tags($addressLines[2]))));
                        
            foreach ($infoLines as $idx => $infoLine){
                if (preg_match('#\s*Tel#', $infoLine) && !strlen($eStore->getPhone())){
                    $eStore->setPhone($sAddress->normalizePhoneNumber($infoLine));
                }
                
                if (preg_match('#ffnungszeit#', $infoLine) && !strlen($eStore->getStoreHours())){
                    $eStore->setStoreHours($sTimes->generateMjOpenings($infoLine));
                }                
                
                if (preg_match('#<strong>([^<]+)</strong>\s*</p>#', $infoLine, $serviceMatch) && !strlen($eStore->getService())){
                    $eStore->setService(trim($serviceMatch[1]));
                }
            }

            $cStores->addElement($eStore);                                                
        }        
 
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}