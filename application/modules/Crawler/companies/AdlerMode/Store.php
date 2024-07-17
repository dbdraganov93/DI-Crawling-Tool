<?php

/*
 * Store Crawler für Adler Mode (ID: 28950)
 */

class Crawler_Company_AdlerMode_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId): Crawler_Generic_Response
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($this->getData($companyId) as $singleStore) {
            if (!preg_match('#^\s*D\s*$#', $singleStore['Land'])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreetAndStreetNumber($this->getValueFromArray($singleStore, 'Straße'))
                ->setZipcode($this->getValueFromArray($singleStore, 'PLZ'))
                ->setCity($this->getValueFromArray($singleStore, 'Ort'))
                ->setPhoneNormalized($this->getValueFromArray($singleStore, 'Telefon'))
                ->setFaxNormalized($this->getValueFromArray($singleStore, 'Telefax'))
                ->setStoreHoursNormalized($this->getOpenings($singleStore));

            $cStores->addElement($eStore);
        }
        return $this->getResponse($cStores, $companyId);
    }

    /**
     * @param int $companyId
     * @return array
     */
    private function getData(int $companyId): array
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $sFtp->connect($companyId);
        foreach ($sFtp->listFiles('.', '#stores\.xls#') as $singleStoreFile) {
            $localStoreFilePath = $sFtp->downloadFtpToDir($singleStoreFile, $localPath);
            break;
        }
        $sFtp->close();

        if (!$localStoreFilePath) {
            return [];
        }
        return $sExcel->readFile($localStoreFilePath, TRUE)->getElement(0)->getData();
    }

    /**
     * @param array $singleStore
     * @param string $pattern
     * @return string
     */
    private function getValueFromArray(array $singleStore, string $pattern): string
    {
        foreach ($singleStore as $key => $item) {
            if (preg_match("#^\s*$pattern\s*$#i", $key) && $item) {
                return $item;
            }
        }
        $this->metaLog("no Result for the pattern: $pattern");
        return '';
    }

    /**
     * @param array $singleStore
     * @return string
     */
    private function getOpenings(array $singleStore): string
    {
        $time = new Marktjagd_Service_Text_Times();
        $strTimes = '';
        $separator = ',';
        foreach ($singleStore as $key => $value) {
            foreach ($time->getWeekdays() as $day) {
                if (!$value || !preg_match("#^\s*$day\s*$#i", $key)) {
                    continue;
                }
                $strTimes .= "$key $value$separator";
            }
        }
        return trim($strTimes, $separator);
    }
}
