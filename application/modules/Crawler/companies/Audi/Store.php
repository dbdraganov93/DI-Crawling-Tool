<?php

/*
 * Store Crawler für Audi (ID: 68767)
 */

class Crawler_Company_Audi_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://dealersearch.audi.com/';
        $searchUrl = $baseUrl . 'api/json/v2/audi-deu/city?q=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zip', 25);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson()->partners;

            if ($jStores) {
                foreach ($jStores as $singleJStore) {
                    if (!preg_match('#DEU#', $singleJStore->countryCode)) {
                        continue;
                    }
                    
                    $strTimes = '';
                    if (preg_match('#Verkauf\s*<br[^>]*>\s*</strong>(.+?)<strong#', $singleJStore->notes->openingHoursHTML, $storeHoursMatch)) {
                        $strTimes = $storeHoursMatch[1];
                    } elseif (preg_match('#Showroom\s*</strong>\s*<br[^>]*>(.+?)</p#', $singleJStore->notes->openingHoursHTML, $storeHoursMatch)) {
                        $strTimes = $storeHoursMatch[1];
                    }

                    $eStore = new Marktjagd_Entity_Api_Store();

                    $eStore->setFaxNormalized($singleJStore->contactDetails->fax->national)
                            ->setEmail($singleJStore->contactDetails->email)
                            ->setPhoneNormalized($singleJStore->contactDetails->phone->national)
                            ->setWebsite($singleJStore->url)
                            ->setLatitude($singleJStore->address->latitude)
                            ->setLongitude($singleJStore->address->longitude)
                            ->setStreetAndStreetNumber($singleJStore->address->street)
                            ->setZipcode($singleJStore->address->zipCode)
                            ->setCity($singleJStore->address->city)
                            ->setStoreHoursNormalized($strTimes);

                    $cStores->addElement($eStore);
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
