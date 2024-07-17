<?php

/**
 * Store Crawler für Phone House (ID: 28900)
 */
class Crawler_Company_PhoneHouse_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.phonehouse.de/';
        $searchUrl = $baseUrl . 'api/rest.php/dealer/plz/'
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP . '.json';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', '15');
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jsonStores = $sPage->getPage()->getResponseAsJson();
            if (!count($jsonStores)) {
                continue;
            }
            foreach ($jsonStores as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setTitle($singleJStore->varName)
                        ->setText('Shopleiter / -in: ' . $singleJStore->varFranchiseName)
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->varStreet)))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->varStreet)))
                        ->setZipcode($singleJStore->varCityCode)
                        ->setCity($singleJStore->varCity)
                        ->setPhone($sAddress->normalizePhoneNumber($singleJStore->varTelefon))
                        ->setEmail($singleJStore->varEmail)
                        ->setStoreHours($sTimes->generateMjOpenings($singleJStore->varOpenHours))
                        ->setLatitude($singleJStore->dblLatitude)
                        ->setLongitude($singleJStore->dblLongitude)
                        ->setStoreNumber($eStore->getHash(true));
                
                // Store-Typen den Vertriebsbereichen zuordnen
                // Samung Stores erhalten keine regulären Shopartikel
                if (preg_match('#Samsung#i', $eStore->getTitle())){
                    $eStore->setDistribution('Samsung Store');
                } elseif (preg_match('#Phone House Shop#i', $eStore->getTitle())){
                    $eStore->setDistribution('Phone House Shop');
                } else {
                    $eStore->setDistribution('Shop');
                }
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}