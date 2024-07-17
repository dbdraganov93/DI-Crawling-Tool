<?php
/**
 * Store crawler für Aldi NL (ID: 81940)
 */

class Crawler_Company_AldiNl_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://locator.uberall.com/';
        $searchUrl = $baseUrl . 'api/storefinders/ALDINORDNL_8oqeY3lnn9MTZdVzFn4o0WCDVTauoZ/' .
            'locations/all?v=20230110&language=nl&' .
            'fieldMask=lat&fieldMask=lng&fieldMask=name&' .
            'fieldMask=country&fieldMask=city&fieldMask=streetAndNumber&' .
            'fieldMask=zip';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->response->locations as $singleJStore) {
            if (!preg_match('#NL#', $singleJStore->country)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lng)
                ->setCity($singleJStore->city)
                ->setStreetAndStreetNumber($singleJStore->streetAndNumber)
                ->setZipcode($singleJStore->zip);

            $cStores->addElement($eStore);

        }

        return $this->getResponse($cStores);
    }
}
