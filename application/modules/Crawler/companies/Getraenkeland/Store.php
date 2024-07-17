<?php

/* 
 * Store Crawler für Getränkeland (ID: 29134)
 */

class Crawler_Company_Getraenkeland_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.getraenkeland.com/';
        $searchUrl = $baseUrl . '/typo3temp/assets/vhs-assets-addresspoints-autocomplete.js';
        $sPage = new Marktjagd_Service_Input_Page();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        $localMappingFile = $sFtp->downloadFtpToDir('Offerista_Standorte_Getraenkeland_Region_12012024.xls', $localPath);
        $sFtp->close();

        $aStores = $sPss->readFile($localMappingFile, TRUE)->getElement(0)->getData();
        $aStoreAssignment = [];
        foreach ($aStores as $singleStore) {
            $aStoreAssignment[str_pad($singleStore['zipcode'], 5, '0', STR_PAD_LEFT) . strtolower($singleStore['street_number'])] = [
                'region' => $singleStore['region'],
                'id' => $singleStore['UID']];
        }

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#addLayer\(([^;]+?)\);#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . 'No store list found.');
        }

        $jStoreUrls = json_decode($storeListMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStoreUrls as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setLatitude($singleJStore[0])
                ->setLongitude($singleJStore[1])
                ->setStreetAndStreetNumber($singleJStore[2])
                ->setZipcode($singleJStore[3])
                ->setCity($singleJStore[4])
                ->setWebsite($baseUrl . trim($singleJStore[5], '/'))
                ->setStoreNumber($aStoreAssignment[$eStore->getZipcode() . $eStore->getStreetNumber()]['id'])
                ->setDistribution($aStoreAssignment[$eStore->getZipcode() . $eStore->getStreetNumber()]['region']);

            if (strlen($eStore->getWebsite())) {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#ffnungszeiten</h3>\s*<table[^>]*>(.+?)</table>#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }

            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}
