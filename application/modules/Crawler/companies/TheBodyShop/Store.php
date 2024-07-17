<?php

/*
 * Store Crawler für The Body Shop (ID: 22302)
 */

class Crawler_Company_TheBodyShop_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.thebodyshop.com/';
        $searchUrl = $baseUrl . 'de-de/store-finder/search?country=DE';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->stores as $singleStoreData) {
            if (!preg_match('#DE#', $singleStoreData->country->isocode)) {
                continue;
            }
            
            $strTimes = '';
            foreach ($singleStoreData->open as $singleDay => $aTimes) {
                if (strlen($singleDay) > 2 || !count($aTimes)) {
                    continue;
                }
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= $singleDay . ' ' . implode('-', $aTimes);
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setZipcode($singleStoreData->zip)
                    ->setPhoneNormalized($singleStoreData->number)
                    ->setCity($singleStoreData->city)
                    ->setLatitude($singleStoreData->latlong[0])
                    ->setLongitude($singleStoreData->latlong[1])
                    ->setStreetAndStreetNumber($singleStoreData->address)
                    ->setStoreHoursNormalized($strTimes);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
