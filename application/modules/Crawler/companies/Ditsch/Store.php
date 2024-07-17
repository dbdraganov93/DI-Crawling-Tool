<?php

/**
 * Store Crawler für Ditsch (ID: 359)
 */
class Crawler_Company_Ditsch_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://api.valora-stores.com/';
        $searchUrl = $baseUrl . '_index.php?key=mnHbfN7ujh6Gb8uHJngzBgfVfbVzhMnP&a=data';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->items as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore[2])
                    ->setLatitude($singleJStore[0])
                    ->setLongitude($singleJStore[1])
                    ->setStreet($singleJStore[7])
                    ->setStreetNumber($singleJStore[8])
                    ->setCity($singleJStore[11])
                    ->setZipcode($singleJStore[10])
                    ->setPhoneNormalized($singleJStore[12]);

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
