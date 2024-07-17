<?php
/**
 * Store Crawler für Eucerin (ID: 81162)
 */

class Crawler_Company_Eucerin_Store extends Crawler_Generic_Company
{
    private const EXCEL_NAME = 'GEO-DATEN_Rabatt-Aktionskunden 220422_Sonne3EUR.xlsx';

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $cStores = new Marktjagd_Collection_Api_Store();

        $localPath = $sFtp->connect('81162', true);
        foreach ($sFtp->listFiles() as $listFile) {
            if (preg_match('#' . self::EXCEL_NAME . '#', $listFile)) {
                $referenceFile = $sFtp->downloadFtpToDir($listFile, $localPath);
                break;
            }
        }

        $storeData = $sPss->readFile($referenceFile, true)->getElement(0)->getData();

        foreach ($storeData as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore
                ->setStreetAndStreetNumber($singleStore['Address'])
                ->setZipcode($singleStore['ZIP code'])
                ->setCity($singleStore['City'])
            ;

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}
