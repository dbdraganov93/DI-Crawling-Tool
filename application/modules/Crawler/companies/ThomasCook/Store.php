<?php

/*
 * Store Crawler für Thomas Cook (ID: 71248)
 */

class Crawler_Company_ThomasCook_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sGen = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'http://www.thomascook-reisebuero.de';
        $searchUrl = $baseUrl . '/agencysearch?search='. $sGen::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $aUrl = $sGen->generateUrl($searchUrl, 'zip');

        foreach ($aUrl as $url) {
            $sPage->open($url);
            $json = $sPage->getPage()->getResponseAsJson();

            foreach ($json as $jsonElement) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($jsonElement->ID);
                $eStore->setTitle($jsonElement->name);
                $eStore->setStreetAndStreetNumber($jsonElement->street);
                $eStore->setZipcode(str_pad($jsonElement->postalcode, 5, STR_PAD_LEFT));
                $eStore->setCity($jsonElement->location);
                $eStore->setPhoneNormalized($jsonElement->phone);
                $eStore->setFaxNormalized($jsonElement->fax);
                $eStore->setEmail($jsonElement->mail);
                $eStore->setLatitude($jsonElement->latitude);
                $eStore->setLongitude($jsonElement->longitude);
                $eStore->setWebsite('http://' . $jsonElement->url);

                $sOpening = '';
                foreach ($jsonElement->opening as $partOpening) {

                    if (strlen($sOpening)) {
                        $sOpening .= ', ';
                    }
                    $sOpening .= $partOpening->day . ' ' . $partOpening->time;
                }

                $eStore->setStoreHoursNormalized($sOpening);
                $eStore->setLogo($baseUrl . $jsonElement->logo);
                $cStores->addElement($eStore, true);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}