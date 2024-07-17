<?php

/*
 * Store Crawler für Getränke Hoffmann (ID: 29135)
 */

class Crawler_Company_GetraenkeHoffmann_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        $aCompany = [
            '29135' => 'Getränke Hoffmann',
            '29083' => 'Huster',
            '29133' => 'Trink & Spare'
        ];

        $baseUrl = 'https://www.getraenke-hoffmann.de/';
        $searchUrl = $baseUrl . 'nearest_branches?postcode=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 5);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            if (!$jStores->branches) {
                continue;
            }
            foreach ($jStores->branches as $singleJStore) {
                $attributeToCheck = $singleJStore->gh_marke;
                if ($attributeToCheck != $aCompany[$companyId]) {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->filialnr)
                    ->setStreetAndStreetNumber($singleJStore->street)
                    ->setCity($singleJStore->locality)
                    ->setZipcode($singleJStore->postal_code)
                    ->setDistribution($singleJStore->hauptregion . ',' . $singleJStore->unterregion . ',' . $singleJStore->werberegion);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores);
    }

}
