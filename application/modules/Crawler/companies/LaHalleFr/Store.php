<?php
/**
 * Store Crawler für La Halle Fr (ID: 72341)
 */

class Crawler_Company_LaHalleFr_Store extends Crawler_Generic_Company
{
    private $_openingsKey = 'openings';

    public function crawl($companyId)
    {
        $filePattern = '#.*stores\.xlsx$#';

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($this->getData($companyId, $filePattern) as $store) {

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($store[0])
                ->setTitle($store["Names of the stores"])
                ->setStreetAndStreetNumber($store['Adresse Postale'])
                ->setZipcode($store['zip codes'])
                ->setCity($store['cities'])
                ->setPhoneNormalized($store['TELEPHONE'])
                ->setStoreHoursNormalized($store[$this->_openingsKey]);

            $cStores->addElement($eStore);
        }
        return $this->getResponse($cStores, $companyId);
    }

    /**
     * @param int $companyId
     * @param string $filePattern
     * @return array
     * @throws PHPExcel_Reader_Exception
     */
    private function getData(int $companyId, string $filePattern): array
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $sFtp->connect($companyId);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (!preg_match($filePattern, $singleFile)) {
                continue;
            }
            $localFile = $sFtp->downloadFtpToCompanyDir($singleFile, $companyId);
            $rawData = $sExcel->getDataFromExcelFilePath($localFile);
            if (!count($rawData)) {
                throw new Exception("no Data from File " . basename($localFile) . " available");
            }
            return $this->getFormattedData($rawData);

        }
        throw new Exception("no Data File available (pattern: $filePattern");
    }

    /**
     * @param array $rawData
     * @return array
     */
    private function getFormattedData(array $rawData): array
    {
        $openingsHead = preg_grep("#horaires#", $rawData[0]);
        $head = array_diff_key($rawData[0], $openingsHead);

        $formattedData = [];
        foreach (array_slice($rawData, 1) as $storeData) {
            $formattedStoreData = [];
            $i = 0;
            foreach (array_intersect_key($storeData, $head) as $key => $item) {
                $formattedStoreData[$head[$key] ?: $i] = $item;
                $i++;
            }
            $formattedStoreData[$this->_openingsKey] = $this->getOpenings(array_intersect_key($storeData, $openingsHead), $openingsHead);
            $formattedData[] = $formattedStoreData;
        }
        return $formattedData;
    }

    /**
     * @param array $data
     * @param array $header
     * @return string
     */
    private function getOpenings(array $data, array $header): string
    {
        $openings = [];
        foreach ($this->getMappedDays($header) as $day => $timeKeys) {
            $openings[] = $day . $this->getOpening(array_intersect_key($data, $timeKeys));
        }
        return implode(',', $openings);
    }

    /**
     * @param array $header
     * @return array
     */
    private function getMappedDays(array $header): array
    {
        $daysMapping = [
            'lundi' => 'Mo',
            'mardi' => 'Di',
            'mercredi' => 'Mi',
            'jeudi' => 'Do',
            'vendredi' => 'Fr',
            'samedi' => 'Sa',
            'dimanche' => 'So',
        ];
        $days = [];
        foreach ($header as $key => $head) {
            if (!preg_match('#horaires\s*([^$]*)#', $head, $match)) {
                continue;
            }
            $days[$daysMapping[$match[1]]][$key] = $key;
        }
        return $days;
    }

    /**
     * @param array $times
     * @return string
     */
    private function getOpening(array $times): string
    {
        $ret = '';
        $i = 0;
        foreach (array_filter($times) as $time) {
            $separator = ' ';
            if ($i % 2) {
                $separator = "-";
            }
            $ret .= $separator . $time;
            $i++;
        }
        return $ret;
    }
}