<?php

/**
 * Store Crawler für BASE (ID: 28878)
 */
class Crawler_Company_Base_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://shopsuche.base.de/';
        $searchUrl = $baseUrl . 'Handlers/FindShops.ashx?Marke=base'
                . '&Lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
                . '&Lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aLinks = $sGen->generateUrl($searchUrl, 'coords', 0.1);
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($aLinks as $singleLink) {
            $sPage->open($singleLink);
            $page = $sPage->getPage()->getResponseBody();
            if (strlen($page) <= 10) {
                continue;
            }
            $jStores = json_decode(preg_replace('#(Shop[0-9]+)#', '"$1"', $page));

            foreach ($jStores as $jSingleStore) {
                $strTime = 'Mo ' . $jSingleStore->Montag . ','
                        . 'Di ' . $jSingleStore->Dienstag . ', '
                        . 'Mi ' . $jSingleStore->Mittwoch . ', '
                        . 'Do ' . $jSingleStore->Donnerstag . ', '
                        . 'Fr ' . $jSingleStore->Freitag . ', '
                        . 'Sa ' . $jSingleStore->Samstag . ', '
                        . 'So ' . $jSingleStore->Sonntag;
                
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($jSingleStore->ID)
                        ->setLatitude($jSingleStore->Latitude)
                        ->setLongitude($jSingleStore->Longitude)
                        ->setEmail($jSingleStore->email)
                        ->setPhone($sAddress->normalizePhoneNumber($jSingleStore->telefon))
                        ->setFax($sAddress->normalizePhoneNumber($jSingleStore->fax))
                        ->setCity($jSingleStore->ort)
                        ->setZipcode($jSingleStore->plz)
                        ->setStreet($sAddress->extractAddressPart('street', $jSingleStore->strasse))
                        ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $jSingleStore->strasse))
                        ->setStoreHours($sTimes->generateMjOpenings($strTime));
                
                if ($eStore->getStoreNumber() == '1220') {
                    $eStore->setPhone('078418340936')
                            ->setFax('078418340937');
                }
                
                $cStores->addElement($eStore, true);               
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
