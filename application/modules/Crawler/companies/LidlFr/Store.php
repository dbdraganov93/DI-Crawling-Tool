<?php

/**
 * Store Crawler für Lidl FR (ID: 72305)
 */
class Crawler_Company_LidlFr_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sGen = new Marktjagd_Service_Generator_Url();
        $searchUrl = 'https://spatial.virtualearth.net/REST/v1/data/717c7792c09a4aa4a53bb789c6bb94ee/'
            . 'Filialdaten-FR/Filialdaten-FR?spatialFilter=nearby('
            . $sGen::$_PLACEHOLDER_LAT . ',' . $sGen::$_PLACEHOLDER_LON . ',1000)'
            . '&$filter=Adresstyp%20Eq%201&$top=51&$format=json&$skip=0'
            . '&key=AgC167Ojch2BCIEvqkvyrhl-yLiZLv6nCK_p0K1wyilYx4lcOnTjm6ud60JnqQAa&Jsonp=displayResultStores';

        $sPage = new Marktjagd_Service_Input_Page();

        $page = $sPage->getPage();
        $page->setTimeout(120);
        $sPage->setPage($page);

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.3, 'FR');

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $page = preg_replace('#^displayResultStores\(#', '', $page);
            $page = preg_replace('#\)$#', '', $page);

            $jStores = json_decode($page);

            foreach ($jStores->d->results as $singleStore) {
                if ($singleStore->CountryRegion != 'FR') {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleStore->EntityID)
                    ->setStreetAndStreetNumber($singleStore->AddressLine, 'FR')
                    ->setZipcode($singleStore->PostalCode)
                    ->setCity(ucwords(strtolower($singleStore->Locality)))
                    ->setLatitude($singleStore->Latitude)
                    ->setLongitude($singleStore->Longitude)
                    ->setDistribution($singleStore->AR)
                    ->setStoreHoursNormalized($singleStore->OpeningTimes, 'text', TRUE, 'fra');

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
