<?php

/*
 * Store Crawler für Buffalo Shoe Shop (ID: 71843)
 */

class Crawler_Company_Buffalo_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.buffalo.de/';
        $searchUrl = $baseUrl . 'buffalo/filialen/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#data-googlemapsstores\s*=\s*\'([^\']+?)\'#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $jStores = json_decode($storeListMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!$singleJStore->active || !preg_match('#DE#', $singleJStore->countryCode)) {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($singleJStore->street)
                    ->setZipcode(trim($singleJStore->zip))
                    ->setCity($singleJStore->city)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setFaxNormalized($singleJStore->fax)
                    ->setEmail($singleJStore->email)
                    ->setStoreHoursNormalized($singleJStore->openingHours);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
