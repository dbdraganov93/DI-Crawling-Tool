<?php

/**
 * Store Crawler für Vom Fass (ID: 29132)
 */
class Crawler_Company_VomFass_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.vomfass.de/';
        $searchUrl = $baseUrl . 'storelocator/index/storeList';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            // nur Standorte in Deutschland erfassen
            if (!(47.2 <=  $singleJStore->lat 
                    && $singleJStore->lat <= 57.2                
                    && 5.8 <=  $singleJStore->lng 
                    && $singleJStore->lng <= 15.2 
                    && strlen($singleJStore->postal) == 5)
                ){
                continue;
            }
            
            if ($singleJStore->city == "Prag"){
                continue;
            }
            
            $eStore->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)                    
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->address)))
                    ->setStreetNumber($sAddress->normalizeStreet($sAddress->extractAddressPart('street_number', $singleJStore->address)))
                    ->setCity($singleJStore->city)                    
                    ->setZipcode($singleJStore->postal)
                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->phone))
                    ->setWebsite($singleJStore->store_url)
                    ->setText('Kontakt: ' . $singleJStore->contact_name);                    

            if (preg_match('#vomfass\.de$#', $eStore->getWebsite())){
                $sPage->open('http://' . $eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();
                                
                if (preg_match('#<h4[^>]*>.+?zeiten</h4>\s*<div[^>]*>(.+?)</div>#', $page, $hoursMatch)){
                    $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
                }
                                                
                if (preg_match('#<div[^>]*>.+?Kontakt.+?</div>\s*<div[^>]*>.+?>([^\@^<]+\@[^<]+)<#', $page, $mailMatch)){
                    $eStore->setEmail($mailMatch[1]);
                }
            }
                        
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
