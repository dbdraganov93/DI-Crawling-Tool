<?php

/**
 * Store crawler for Red Zac AT (ID: 72492)
 */

class Crawler_Company_RedZacAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.redzac.at/';
        $storeUrl = $baseUrl . 'feed/offerista/redzac_stores.csv';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $localStoreFile = $sHttp->getRemoteFile($storeUrl, $localPath);

        $aData = $sPss->readFile($localStoreFile, TRUE, ';')->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $aCoords = preg_split('#\s*,\s*#', $singleRow['Geokoordinaten']);

            $strTimes = '';
            foreach ($singleRow as $key => $value) {
                if (!preg_match('#Öffnungszeiten\s*(.+)#', $key, $dayMatch)) {
                    continue;
                }

                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }

                $strTimes .= $dayMatch[1] . ' ' . $value;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleRow['REDZAC_ID'])
                ->setTitle($singleRow['Karriereportal_Händlername'])
                ->setStreetAndStreetNumber($singleRow['Strasse'], 'AT')
                ->setZipcode($singleRow['PLZ'])
                ->setCity($singleRow['Ort'])
                ->setPhoneNormalized($singleRow['Telefon'])
                ->setEmail($singleRow['Email'])
                ->setWebsite($singleRow['Website'])
                ->setLatitude($aCoords[0])
                ->setLongitude($aCoords[1])
                ->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}