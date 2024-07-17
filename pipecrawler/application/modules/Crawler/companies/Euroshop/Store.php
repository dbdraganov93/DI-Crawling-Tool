<?php

/**
 * Store crawler for Euroshop (ID: 22297)
 */

class Crawler_Company_Euroshop_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#Filial#', $singleRemoteFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $strTimes = '';
            foreach ($singleRow as $key => $value) {
                if (!preg_match('#ffnungszeiten\s*\(([^\)]+?)\)#', $key, $dayMatch)) {
                    continue;
                }
                if (strlen($strTimes)) {
                    $strTimes .= ', ';
                }
                $strTimes .= $dayMatch[1] . ' ' . $value;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleRow['Geschäftscode'])
                ->setStreetAndStreetNumber($singleRow['Adresszeile 1'])
                ->setCity($singleRow['Ort'])
                ->setZipcode($singleRow['Postleitzahl'])
                ->setPhoneNormalized($singleRow['Primäre Telefonnummer'])
                ->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}