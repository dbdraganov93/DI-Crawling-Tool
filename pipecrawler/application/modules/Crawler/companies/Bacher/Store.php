<?php

/**
 * Store Crawler für Reformhaus Bacher (ID: 29040)
 */
class Crawler_Company_Bacher_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.reformhaus-bacher.de/';
        $searchUrl = $baseUrl . 'standorte/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        if (!$sPage->open($searchUrl)) {
            throw new Exception ($companyId . ': unable to open store list page.');
        }
        
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*infoPhocaWindow(.+?)\,\s*icon#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception ($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStoreMatch) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#^([0-9]+)[^0-9]#';
            if (!preg_match($pattern, $singleStoreMatch, $storeNoMatch)) {
                throw new Exception ($companyId . ': unable to get store number.');
            }
            
            $pattern = '#<p[^>]*>(.+?)</p#';
            if (!preg_match_all($pattern, $singleStoreMatch, $storeAddressMatches)) {
                throw new Exception ($companyId . ': unable to get store address or times.');
            }

            $pattern = '#src=\\\"/(.+?)\\\"#';
            if (preg_match($pattern, $storeAddressMatches[1][0], $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
                $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeAddressMatches[1][1]);
                $strTime = $storeAddressMatches[1][2];
            } else {
                $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeAddressMatches[1][0]);
                $strTime = $storeAddressMatches[1][1];
            }
            
            $strTime = preg_replace(array('#von\s*#', '#\:\s+#'), array('', ':'), $strTime);
            if ($storeNoMatch[1] == 75) {
                $strTime = preg_replace( '#([a-z])\.\,#', '$1', $strTime);
            }

            $pattern = '#Tel\.?\s*(.+?)<#';
            if (preg_match($pattern, $singleStoreMatch, $telMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($telMatch[1]));
            }
            
            $pattern = '#Fax\.?\s*(.+?)<#';
            if (preg_match($pattern, $singleStoreMatch, $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }
            
            $pattern = '#maps\.LatLng\((.+?)\,(.+?)\)#';
            if (preg_match($pattern, $singleStoreMatch, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                        ->setLongitude($geoMatch[2]);
            }
            
            $eStore->setStoreNumber($storeNoMatch[1])
                    ->setStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress)-2]))
                ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress)-2]))
                ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress)-1]))
                ->setZipcode($sAddress->extractAddressPart('zip', $aAddress[count($aAddress)-1]))
                ->setStoreHours($sTimes->generateMjOpenings($strTime));
            
            if (count($aAddress) == 3) {
                $eStore->setSubtitle($aAddress[0]);
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}