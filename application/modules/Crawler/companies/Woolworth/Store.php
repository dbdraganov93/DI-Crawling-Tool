<?php

/**
 * Store Crawler für Woolworth (ID: 79)
 */

class Crawler_Company_Woolworth_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sEmail = new Marktjagd_Service_Transfer_Email('Woolworth');
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sDrive = new Marktjagd_Service_Input_GoogleDriveRead();
        $sGeo = new Marktjagd_Database_Service_GeoRegion();

        $cStoresApi = $sApi->findStoresByCompany($companyId);
        foreach ($cStoresApi->getElements() as $eStoreApi) {
            $aDists[$eStoreApi->getStoreNumber()] = $eStoreApi->getDistribution();
        }

        $cEmails = $sEmail->generateEmailCollection($companyId);

        foreach ($cEmails->getElements() as $eEmail) {
            $pattern = '#(https:\/\/drive\.google\.com[^\?]+?)\?#s';
            if (!preg_match($pattern, $eEmail->getText(), $urlMatch)) {
                $this->_logger->info($companyId . ': unable to get drive url from mail.');
                continue;
            }

            $aDriveFiles = $sDrive->readDrive($urlMatch[1]);

            foreach ($aDriveFiles as $singleDriveFileId => $singleDriveFileName) {
                if (preg_match('#([W|D]P[^\]]+?)\.xlsx$#', $singleDriveFileName, $distMatch)) {
                    $localStoreFile = $sDrive->downloadFile($singleDriveFileId, APPLICATION_PATH . '/../public/files/tmp/tmp.xlsx');
                    $distribution = $distMatch[1];
                    break;
                }
            }

            $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

            $cStores = new Marktjagd_Collection_Api_Store();
            foreach ($aData as $singleRow) {
                if (array_key_exists($singleRow['Filialen'], $aDists)
                    && !in_array($distribution, preg_split('#\s*,\s*#', $aDists[$singleRow['Filialen']]))) {
                    $aDists[$singleRow['Filialen']] .= ',' . $distribution;
                }
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleRow['Filialen'])
                    ->setCity($singleRow['Ort'])
                    ->setZipcode($singleRow['PLZ'])
                    ->setStreet($singleRow['Straße'])
                    ->setStreetNumber($singleRow['Hausnummer'])
                    ->setDistribution($aDists[$singleRow['Filialen']]);

                if (preg_match('#^\s*Bad\s*$#', $eStore->getCity())) {
                    $dbCity = $sGeo->findCityByZipCode($eStore->getZipcode());
                    $eStore->setCity($dbCity);
                }

                $cStores->addElement($eStore);
            }

        }

        return $this->getResponse($cStores);
    }

}
