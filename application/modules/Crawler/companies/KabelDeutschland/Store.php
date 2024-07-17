<?php

/* 
 * Store Crawler für Kabel Deutschland (ID: 29207)
 */

class Crawler_Company_KabelDeutschland_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://haendlersuche.kabelspezial.de/';
        $searchUrl = $baseUrl . 'jsonp.php?jsoncallback=jQuery172044763355686454753_1432711318899&lat='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&lng='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&umk=100';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 1);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#jQuery[0-9]+\_[0-9]+\((\{.+\})\);#';
            if (!preg_match($pattern, $page, $jsonMatch)) {
                continue;
            }
            
            $jStores = json_decode(preg_replace('#("{3,})#', '"', $jsonMatch[1]));
            
            if (is_null($jStores)) {
                $this->_logger->err($companyId . ': invalid json: ' . $singleUrl);
                continue;
            }
            
            foreach ($jStores as $singleStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setStoreNumber($singleStore->vpkn)
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleStore->strasse)))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleStore->strasse)))
                        ->setZipcode($singleStore->plz)
                        ->setCity($singleStore->ort)
                        ->setEmail($sAddress->normalizeEmail($singleStore->email))
                        ->setPhone($sAddress->normalizePhoneNumber($singleStore->telefon))
                        ->setFax($sAddress->normalizePhoneNumber($singleStore->fax))
                        ->setStoreHours($sTimes->generateMjOpenings($singleStore->offen_mofr . ', ' . $singleStore->offen_sa))
                        ->setWebsite($singleStore->shop_url)
                        ->setLatitude($singleStore->lat)
                        ->setLongitude($singleStore->lng);
                
                if ($eStore->getStoreNumber() == 'PS003901'){
                    $eStore->setStoreHours('Mo-Fr 08:00-12:00, Mo-Fr 16:00 - 20:00, Sa 11:00-15:00');
                }
                
                $cStores->addElement($eStore, true);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}