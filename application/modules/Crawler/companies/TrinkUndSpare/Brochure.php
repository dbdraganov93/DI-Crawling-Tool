<?php
/**
 * Store Crawler for Trink&Spare (ID: 29133)
 */

class Crawler_Company_TrinkUndSpare_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sTimes = new Marktjagd_Service_Text_Times();

        $week = "next";

        $brochures = [];
        $dataFile = '';
        $this->getAndSaveFiles($brochures, $dataFile, $companyId, $sTimes->getWeekNr($week), $sTimes->getWeeksYear($week));

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($this->getAssignment($dataFile) as $brochure => $stores) {

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($brochures[$brochure])
                ->setStoreNumber(implode(", ", $stores))
                ->setBrochureNumber(pathinfo($brochure)["filename"] . '_' . $sTimes->getWeeksYear($week))
                ->setVisibleStart(date('d.m.Y', strtotime("$week week sunday - 1 week")))
                ->setStart(date('d.m.Y', strtotime("$week week monday")))
                ->setEnd(date('d.m.Y', strtotime("$week week saturday")));

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @param $brochures
     * @param $dataFile
     * @param $companyId
     * @param $week
     * @param $year
     * @throws Exception
     */
    private function getAndSaveFiles(&$brochures, &$dataFile, $companyId, $week, $year): void
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $sFtp->connect($companyId);
        foreach ($sFtp->listFiles('./KW' . $week . '_' . $year) as $file) {
            if (preg_match("#\.pdf?#i", $file)) {
                $fileName = pathinfo($file)['filename'];
                $brochures[strtolower($fileName)] = $sFtp->downloadFtpToCompanyDir($file, $companyId);
                continue;
            }
            if (!$dataFile && preg_match("#\.xlsx?#i", $file)) {
                $dataFile = $sFtp->downloadFtpToCompanyDir($file, $companyId);
            }
        }
        $sFtp->close();

        if (!count($brochures)) {
            throw new Exception("No brochures found in folder ftp://29133/KW$week" . "_$year");
        }
        if (!$dataFile) {
            throw new Exception("No brochures assignment files found in folder ftp://29133/KW$week" . "_$year");
        }
    }

    /**
     * @param string $dataFile
     * @return array
     */
    private function getAssignment(string $dataFile): array
    {
        $sExcel = new Marktjagd_Service_Input_PhpSpreadsheet();

        $brochureStores = [];
        foreach ($sExcel->readFile($dataFile, true)->getElement(0)->getData() as $storeAssignment) {

            //////////////////// Testfilialen ////////////////////
            if (!in_array($storeAssignment["Filialnummer"], [15710, 15796,
                10846, 15411, 15416, 17508, 10217, 14332, 15705, 12577, 11870,
                10249, 10639, 14766, 16984, 11290, 11324, 11404, 10722, 11758])) {
                continue;
            }
            //////////////////////////////////////////////////////

            $fileName = pathinfo($storeAssignment["Version"])['filename'];
            $brochureStores[strtolower($fileName)][] = $storeAssignment["Filialnummer"];
        }
        return $brochureStores;
    }
}
