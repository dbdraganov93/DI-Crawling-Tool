<?php
/**
 * Store Crawler für Volkswagen WGW (ID: 73594)
 */

class Crawler_Company_VolkswagenWgw_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://hs.porsche-holding.com/';
        $searchUrl = $baseUrl . 'api/v1/at/dealers/V';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->data as $singleJStore) {
            if (!$singleJStore->has_sales
                || $singleJStore->deleted
                || !preg_match('#at#', $singleJStore->country)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setPhoneNormalized($singleJStore->telephone)
                ->setFaxNormalized($singleJStore->telefax)
                ->setWebsite($singleJStore->url)
                ->setEmail($singleJStore->email)
                ->setStoreHoursNormalized($singleJStore->opening_times)
                ->setStreetAndStreetNumber($singleJStore->street)
                ->setZipcode($singleJStore->zip)
                ->setCity($singleJStore->city)
                ->setStoreNumber($singleJStore->id)
                ->setLatitude($singleJStore->latitude)
                ->setLongitude($singleJStore->longitude)
                ->setLogo($singleJStore->logourl)
                ->setDistribution($singleJStore->federal_state);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}