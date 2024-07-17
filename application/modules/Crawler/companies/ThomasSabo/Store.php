<?php

/**
 * Store Crawler für Thomas Sabo (ID: 69678)
 */
class Crawler_Company_ThomasSabo_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.thomassabo.com/';
        $searchUrl = $baseUrl . 'on/demandware.store/Sites-TS_EURO-Site/de_DE/'
                . 'Shopfinder-GetStores?searchMode=radius&searchPhrase='
                . '&searchDistance=50&lat='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&lng='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'rect', 0.2);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores as $jSingleStore) {
                if (!preg_match('#^(\+49)#', $jSingleStore->phone) || $jSingleStore->category->value != 1) {
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($jSingleStore->ID)
                        ->setPhoneNormalized($jSingleStore->phone)
                        ->setFaxNormalized($jSingleStore->fax)
                        ->setSubtitle($jSingleStore->address2)
                        ->setStreetAndStreetNumber($jSingleStore->address1)
                        ->setCity($jSingleStore->city)
                        ->setZipcode($jSingleStore->postalCode)
                        ->setLongitude($jSingleStore->longitude)
                        ->setLatitude($jSingleStore->latitude)
                        ->setWebsite($jSingleStore->www)
                        ->setStoreHoursNormalized($jSingleStore->storeHours);

                if ((preg_match('#^(c\/o)#', $jSingleStore->address1) && strlen($jSingleStore->address2)) || preg_match('#([0-9]+)([a-z]*)$#i', $jSingleStore->address2)) {
                    $eStore->setSubtitle($jSingleStore->address1)
                            ->setStreetAndStreetNumber($jSingleStore->address2);
                }

                $cStores->addElement($eStore, true);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
