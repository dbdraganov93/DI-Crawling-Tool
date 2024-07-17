<?php

/**
 * Store Crawler für Lidl (ID: 72148)
 */
class Crawler_Company_LidlCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sGen = new Marktjagd_Service_Generator_Url();
        $searchUrl = 'https://spatial.virtualearth.net/REST/v1/data/7d24986af4ad4548bb34f034b067d207/'
            . 'Filialdaten-CH/Filialdaten-CH?spatialFilter=nearby('
            . $sGen::$_PLACEHOLDER_LAT . ',' . $sGen::$_PLACEHOLDER_LON . ',1000)'
            . '&$filter=Adresstyp%20Eq%201&$top=51&$format=json&$skip=0'
            . '&key=AijRQid01hkLFxKFV7vcRwCWv1oPyY5w6XIWJ-LdxHXxwfH7UUG46Z7dMknbj_rL&Jsonp=displayResultStores';

        $sPage = new Marktjagd_Service_Input_Page();

        $page = $sPage->getPage();
        $page->setTimeout(120);
        $sPage->setPage($page);

        $sAddress = new Marktjagd_Service_Text_Address();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.3, 'CH');

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $singleUrl) {
            $this->_logger->info('open ' . $singleUrl);
            $sPage->open($singleUrl);


            $page = $sPage->getPage()->getResponseBody();

            $page = preg_replace('#^displayResultStores\(#', '', $page);
            $page = preg_replace('#\)$#', '', $page);

            $jStores = json_decode($page);

            foreach ($jStores->d->results as $singleStore) {
                // nur Standorte in der Schweiz
                if ($singleStore->CountryRegion != 'CH') {
                    continue;
                }

                $strTimes = preg_replace(['#<a[^>]*>.+#', '#\/\w{2}#'], '', $singleStore->OpeningTimes);

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleStore->EntityID)
                    ->setStreetAndStreetNumber($singleStore->AddressLine, 'CH')
                    ->setZipcode($singleStore->PostalCode)
                    ->setCity($singleStore->Locality)
                    ->setLatitude($singleStore->Latitude)
                    ->setLongitude($singleStore->Longitude)
                    ->setDistribution($singleStore->AR)
                    ->setStoreHoursNormalized($strTimes);

                if ($singleStore->bake == 'bake') {
                    $eStore->setService('täglich frische Backwaren');
                }

                if ($singleStore->NF) {
                    $eStore->setSection('Komplettes Lidl-Sortiment');
                }

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores);
    }
}