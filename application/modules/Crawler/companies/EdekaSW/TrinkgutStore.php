<?php

/** Store crawler for trinkgut Südwest (ID: 82617)
 *
 */

class Crawler_Company_EdekaSW_TrinkgutStore extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect(71668, TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Trinkgut#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            if (!preg_match('#trinkgut#i', $singleRow['VERTRIEBSSCHIENE'])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setTitle($singleRow['BEZEICHNUNG'])
                ->setStoreNumber($singleRow['MARKT_ID'])
                ->setStreetAndStreetNumber($singleRow['STRAßE'])
                ->setZipcode($singleRow['PLZ'])
                ->setCity($singleRow['ORT'])
                ->setPhoneNormalized($singleRow['TELEFON'])
                ->setEmail($singleRow['EMAIL'])
                ->setStoreHoursNormalized($singleRow['STANDARD_ÖFFNUNGSZEITEN'])
                ->setDistribution(preg_replace('#.+\/([^\/]+?)\/index.html#', '$1', $singleRow['URL_HZ']));

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}