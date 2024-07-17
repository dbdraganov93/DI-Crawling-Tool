<?php

/**
 * Store Crawler für Markant (ID: 71251)
 */
class Crawler_Company_Markant_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $aExcelData = $sPss->readFile($this->_getStoreFile(), TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aExcelData as $singleRow) {
            $cStores->addElement($this->_buildStore($singleRow));
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function _getStoreFile(): string
    {
        $localStoreFile = '';
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->connect('28975', TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#markant\.xlsx#i', $singleFile)) {
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
            ->setStreetAndStreetNumber($singleRow['Straße'])
            ->setZipcode($singleRow['PLZ'])
            ->setCity($singleRow['Ort'])
            ->setStoreHours($this->_getOpenHours($singleRow));
    }

    private function _getOpenHours(array $singleRow): string
    {
        $openHour = [];
        $MoFr = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];
        $openHourA = array_slice($singleRow, -2, 2);
        foreach ($MoFr as $day) {
            $openHour[] = $day . ' ' . $this->_formatOpenHour(reset($openHourA));
        }
        $openHour[] = 'Sa ' . $this->_formatOpenHour(end($openHourA));
        return implode(',', $openHour);
    }

    private function _formatOpenHour(string $openHour): string
    {
        return '0' . str_replace('-', ':00-', str_replace(' Uhr', ':00', $openHour));
    }
}
