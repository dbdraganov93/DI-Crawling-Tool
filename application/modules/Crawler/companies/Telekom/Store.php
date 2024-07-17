<?php

/* 
 * Store Crawler für Telekom (ID: 28829)
 */

class Crawler_Company_Telekom_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://tkom-shopfinder-live.pi.mcon.net/';
        $searchUrl = $baseUrl . 'shop/search/viewport?latitude='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&longitude='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON .  '&width=200&height=200';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.2);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson()->rows;
            
            foreach ($jStores as $singleJStore) {
                $pattern = '#tsg#';
                if (!preg_match($pattern, $singleJStore->shop->type)) {
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $strTimes = '';
                foreach ($singleJStore->shop->opening as $day => $times) {
                    foreach ($times as $singleTimes) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $sTimes->convertToGermanDays($day . ' ' . $singleTimes->open . '-' . $singleTimes->close);
                    }
                }
                
                $eStore->setStoreNumber($singleJStore->shop->vpnr)
                        ->setWebsite($singleJStore->shop->url)
                        ->setLatitude($singleJStore->shop->location->latitude)
                        ->setLongitude($singleJStore->shop->location->longitude)
                        ->setZipcode($singleJStore->shop->address->postalCode)
                        ->setCity($singleJStore->shop->address->city)
                        ->setStreet($singleJStore->shop->address->street)
                        ->setStreetNumber($singleJStore->shop->address->streetNumber)
                        ->setPhoneNormalized($singleJStore->shop->address->phoneCode . $singleJStore->shop->address->phoneNumber)
                        ->setFaxNormalized($singleJStore->shop->address->faxCode . $singleJStore->shop->address->faxNumber)
                        ->setStoreHours($strTimes);
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}