<?php

/*
 * Store Crawler für Küche & Co (ID: 28508)
 */

class Crawler_Company_KuecheCo_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect('28508', TRUE);

        $aCompany = [
            '28508' => '#^K\&C$#',
            '72509' => '#^K\&C_AT$#'
        ];

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xlsx?$#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            if (!preg_match($aCompany[$companyId], $singleRow['businessIdentifier'])
                || !preg_match('#ACTIVE#', $singleRow['status'])) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleRow['identifier'])
                ->setStreetAndStreetNumber($singleRow['streetAndNumber'])
                ->setZipcode($singleRow['zip'])
                ->setCity($singleRow['city'])
                ->setLatitude($singleRow['lat'])
                ->setLongitude($singleRow['lng'])
                ->setPhoneNormalized($singleRow['phone'])
                ->setFaxNormalized($singleRow['fax'])
                ->setWebsite($singleRow['website'])
                ->setText($singleRow['descriptionLong'])
                ->setStoreHoursNormalized(preg_replace('#\=#', ' ', $singleRow['openingHours']))
                ->setStoreHoursNotes($singleRow['openingHoursNotes'])
                ->setService(preg_replace('#\s*,\s*#', ', ', $singleRow['services']));

            $cStores->addElement($eStore, TRUE);
        }

        return $this->getResponse($cStores, $companyId);
    }
}