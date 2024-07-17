<?php
/**
 * Store crawler für Aldi FR (ID: 73615)
 */

class Crawler_Company_AldiFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://locator.uberall.com/';
        $searchUrl = $baseUrl . 'api/storefinders/ALDINORDFR_Mmljd17th8w26DMwOy4pScWk4lCvj5/' .
            'locations/all?v=20230110&language=fr&' .
            'fieldMask=lat&fieldMask=lng&fieldMask=name&' .
            'fieldMask=country&fieldMask=city&fieldMask=streetAndNumber&' .
            'fieldMask=zip';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->response->locations as $singleJStore) {
            if (!preg_match('#FR#', $singleJStore->country)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lng)
                ->setTitle($singleJStore->name)
                ->setCity($singleJStore->city)
                ->setStreetAndStreetNumber($singleJStore->streetAndNumber, 'FR')
                ->setZipcode($singleJStore->zip);

            $cStores->addElement($eStore);

        }

        return $this->getResponse($cStores);
    }
}
