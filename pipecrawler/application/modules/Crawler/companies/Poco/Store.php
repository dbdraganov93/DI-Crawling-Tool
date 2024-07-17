<?php

/**
 * Store Crawler für POCO (ID: 197)
 */
class Crawler_Company_Poco_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        $localStoreFile = '';
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#standort#i', $singleRemoteFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $aTimes = $this->prepareStoreHours($singleRow);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleRow['Entitäts-ID'])
                ->setStreetAndStreetNumber($singleRow['Adresse > Zeile 1'])
                ->setCity($singleRow['Adresse > Stadt'])
                ->setZipcode($singleRow['Adresse > Postleitzahl'])
                ->setStoreHoursNormalized(implode(',', $aTimes));

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

    private function prepareStoreHours($singleRow): ?array
    {
        $aTimes = [];
        foreach ($singleRow as $key => $value) {
            if (!preg_match('#Geschäftszeiten\s*>\s*(.+)#', $key, $dayMatch)) {
                continue;
            }
            $aTimes[] = $dayMatch[1] . ' ' . $value;
        }

        return $aTimes;
    }
}
