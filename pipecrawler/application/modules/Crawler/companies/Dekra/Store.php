<?php

/*
 * Store Crawler für Dekra (ID: 72045)
 */

class Crawler_Company_Dekra_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.dekra.de/';
        $searchUrl = $baseUrl . 'locationSearch?origin_latitude=51.0491316&origin_longitude=13&fq=type:location.(location_type:Automobil%20Pr%C3%BCfwesen%7Clocation_type:Begutachtungsstellen%20f%C3%BCr%20Fahreignung%7Clocation_type:Arbeitsmedizin%7Clocation_type:Bildung%7Clocation_type:Zeitarbeit%7Clocation_type:none).geo_longitude:%5B5;16%5D.geo_latitude:%5B44;55%5D';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->dekraLocationSearch->results as $singleJStore) {
            if (!preg_match('#Deutschland#', $singleJStore->country)) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setCity($singleJStore->city)
                ->setLatitude($singleJStore->geo_latitude)
                ->setWebsite($singleJStore->link_website)
                ->setLongitude($singleJStore->geo_longitude)
                ->setStreetNumber($singleJStore->house_number)
                ->setZipcode($singleJStore->zip_code)
                ->setService(implode(', ', $singleJStore->special_services))
                ->setPhoneNormalized($singleJStore->phone)
                ->setStreet($singleJStore->street)
                ->setFaxNormalized($singleJStore->fax)
                ->setEmail($singleJStore->email);

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
