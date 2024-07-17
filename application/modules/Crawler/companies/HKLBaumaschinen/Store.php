<?php

class Crawler_Company_HKLBaumaschinen_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.hkl-baumaschinen.de/';
        $searchUrl = $baseUrl . 'app/v2/niederlassung';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#Deutschland#', $singleJStore->land)) {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->id)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->strasse)))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->strasse)))
                    ->setZipcode($singleJStore->plz)
                    ->setCity($singleJStore->ort)
                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->tel))
                    ->setEmail($singleJStore->email)
                    ->setStoreHours($sTimes->generateMjOpenings($singleJStore->offen_text))
                    ->setImage($baseUrl . $singleJStore->image_teaser)
                    ->setWebsite($singleJStore->website);
            
            $cStores->addElement($eStore);
            
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
