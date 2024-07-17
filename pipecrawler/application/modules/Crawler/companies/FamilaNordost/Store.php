<?php

/**
 * Store Crawler für Famila Nordost (ID: 28975)
 */
class Crawler_Company_FamilaNordost_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $aExcelData = $sPss->readFile($this->_getStoreFile($companyId), TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aExcelData as $singleRow) {
           $cStores->addElement($this->_buildStore($singleRow));
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function _getStoreFile(int $companyId): string
    {
        $localStoreFile = '';
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#famila\.xlsx#i', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }
        $sFtp->close();

        return $localStoreFile;
    }

    private function _buildStore(array $singleRow): Marktjagd_Entity_Api_Store
    {
        $eStore = new Marktjagd_Entity_Api_Store();
        return $eStore->setStoreNumber($singleRow['Nr.'])
            ->setTitle('famila' . " " . $singleRow['Ort'])
            ->setStreetAndStreetNumber($singleRow['Straße'])
            ->setZipcode($singleRow['PLZ'])
            ->setCity($singleRow['Ort'])
            ->setStoreHours($this->_getOpenHours($singleRow));
    }

    private function _getOpenHours(array $singleRow): string
    {
        $openHour = [];
        $MoFr = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        $openHourA = array_slice($singleRow, -2, 2);
        foreach ($MoFr as $day) {
            $openHour[] = $day . ' ' . str_replace(' - ', '-', reset($openHourA));
        }
        return implode(',', $openHour);
    }
}
