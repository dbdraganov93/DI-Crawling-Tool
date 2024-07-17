<?php

/**
 * Store Crawler für Lidl AT (ID: 73217)
 */
class Crawler_Company_LidlAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $searchUrl = 'https://spatial.virtualearth.net/REST/v1/data/d9ba533940714d34ac6c3714ec2704cc/'
            . 'Filialdaten-AT/Filialdaten-AT?spatialFilter=nearby('
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . ',' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . ',1000)'
            . '&$filter=Adresstyp%20Eq%201&$top=51&$format=json&$skip=0'
            . '&key=Ailqih9-jVv2lUGvfCkWmEFxPjFBNcEdqZ3lK_6jMMDDtfTYu60SwIaxs32Wtik2&Jsonp=displayResultStores';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $sFtp->connect('dataAt');
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $localAssignmentFile = $sFtp->downloadFtpToDir('/dataAt/at_counties.csv', $localPath);
        $sFtp->close();

        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();
        $aData = $sPhpSpreadsheet->readFile($localAssignmentFile, TRUE, ';')->getElement(0)->getData();
        $aCounties = [];
        foreach ($aData as $singleColumn) {
            $aCounties[$singleColumn['zipcode']] = $singleColumn['county'];
        }

        $page = $sPage->getPage();
        $page->setTimeout(120);
        $sPage->setPage($page);

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.3, 'AT');

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $page = preg_replace('#^displayResultStores\(#', '', $page);
            $page = preg_replace('#\)$#', '', $page);

            $jStores = json_decode($page);

            foreach ($jStores->d->results as $singleStore) {
                if ($singleStore->CountryRegion != 'AT') {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleStore->EntityID)
                    ->setStreetAndStreetNumber($singleStore->AddressLine, 'AT')
                    ->setZipcode($singleStore->PostalCode)
                    ->setCity(ucwords(strtolower($singleStore->Locality)))
                    ->setLatitude($singleStore->Latitude)
                    ->setLongitude($singleStore->Longitude)
                    ->setDistribution($singleStore->AR)
                    ->setStoreHoursNormalized($singleStore->OpeningTimes);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

}
