<?php

/**
 * Store Crawler für Roller (ID: 76)
 */
class Crawler_Company_Roller_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xlsx?$#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $aData = $sPhpSpreadsheet->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleStore) {
            $strStoreHours = '';
            foreach ($singleStore as $key => $value) {
                if (!preg_match('#Öffnungszeiten\s*\(([^\)]+?)\)#', $key, $dayMatch)) {
                    continue;
                }
                if (strlen($strStoreHours)) {
                    $strStoreHours .= ',';
                }

                $strStoreHours .= ucwords($dayMatch[1]) . ' ' . $value;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($singleStore['Adresszeile 1'])
                ->setCity($singleStore['Ort'])
                ->setZipcode($singleStore['Postleitzahl'])
                ->setPhoneNormalized($singleStore['Primäre Telefonnummer'])
                ->setStoreHoursNormalized($strStoreHours);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

}
