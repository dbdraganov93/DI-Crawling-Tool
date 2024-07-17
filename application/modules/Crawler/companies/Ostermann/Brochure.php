<?php

/*
 * Prospekt Crawler für Ostermann und Trends by Ostermann (ID's: 68899, 69867)
 */

class Crawler_Company_Ostermann_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aPattern = array(
            '68899' => '#^ost#i',
            '69867' => '#^tre#i'
        );

        $aStoreNumbers = array(
            'B' => 'BOT',
            'H' => 'HAA',
            'L' => 'LEV',
            'R' => 'RE',
            'W' => 'WIT'
        );

        $sFtp->connect('68899');

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $localFilePath = $sFtp->generateLocalDownloadFolder($companyId);
        foreach ($sFtp->listFiles('.', $aPattern[$companyId]) as $singleFolder) {
            $pattern = '#(.+)[^\d+](\d{1,2}\.\d{1,2}\.?)(\d{4})?\s*[bis|-]\s*(\d{1,2}\.\d{1,2}\.)(\d{2,4})?$#';
            if (!preg_match($pattern, $singleFolder, $validityMatch) || !$sTimes->isDateAhead($validityMatch[4] . $validityMatch[5])) {
                continue;
            }

            if (!preg_match('#\d{4}$#', $validityMatch[3])) {
                $validityMatch[3] = date('Y');
                if (strtotime($validityMatch[2] . $validityMatch[3]) < strtotime('now')) {
                    //$validityMatch[3] = date('Y', strtotime('next year'));
                }
            }

            $einlegerBrochures = [];
            $aFiles = [];
            foreach ($sFtp->listFiles($singleFolder, '#\.pdf$#') as $singleFile) {
                if (preg_match('#(\d{2})\d{2}_Einleger|E_(\d{2}|Rueckseite)#', $singleFile, $einlegerPageMatch)) {
                    $pageNo = (int) $einlegerPageMatch[1];
                    if (!strlen($einlegerPageMatch[1]) && strlen($einlegerPageMatch[2])) {
                        $pageNo = (int) $einlegerPageMatch[2];
                    }
                    $einlegerBrochures[$pageNo] = $sFtp->downloadFtpToDir($singleFile, $localFilePath);
                    continue;
                }

                $aFiles[] = $sFtp->downloadFtpToDir($singleFile, $localFilePath);
            }
            sort($aFiles);
            ksort($einlegerBrochures);

            // Sort also "Rueckseite" pages to the end
            foreach ($einlegerBrochures as $key => $einlegerBrochure) {
                if (preg_match('#(\d{2})\d{2}_Einleger|E_(Rueckseite)#', $einlegerBrochure)) {
                    unset($einlegerBrochures[$key]);
                    array_push($einlegerBrochures, $einlegerBrochure);
                }
            }

            $startDate = $this->validateAndConvertDate($validityMatch[2] . $validityMatch[3]);
            $endDate = $this->validateAndConvertDate($validityMatch[4] . $validityMatch[5]);

            // Put the eileger to the end of the the "main" brochure
            $mergedBrochureEileger = array_merge($aFiles, $einlegerBrochures);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($sPdf->merge($mergedBrochureEileger, $localFilePath))
                ->setBrochureNumber($this->getBrochureNumber($validityMatch) . $startDate)
                ->setTitle('Neue Möbel wirken Wunder.')
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet')
                ->setStoreNumber($this->getStores($singleFolder, $aStoreNumbers));

            $cBrochures->addElement($eBrochure);

            // deactivated this block for the moment
            if (false) {
                $eBrochure = new Marktjagd_Entity_Api_Brochure();
                $eBrochure->setUrl($sPdf->merge($einlegerBrochures, $localFilePath))
                    ->setBrochureNumber('Einleger_' . $startDate)
                    ->setTitle('Sonderbeilage')
                    ->setStart($startDate)
                    ->setEnd($endDate)
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet')
                    ->setStoreNumber($this->getStores($singleFolder, $aStoreNumbers));

                $cBrochures->addElement($eBrochure);
            }
        }
        $sFtp->close();

        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @param $singleFolder
     * @param $aStoreNumbers
     * @return string
     */
    private function getStores($singleFolder, $aStoreNumbers)
    {
        if (!preg_match('#alle\s*standorte#i', $singleFolder) && preg_match('#[^TRENDS|OSTERMANN|a-z]([A-Z]+)[^a-z]#', $singleFolder, $storeMatch)) {
            $aNewStoreNumbers = preg_split('#\s*#', $storeMatch[1]);
            foreach ($aNewStoreNumbers as &$singleNewStoreNumber) {
                $singleNewStoreNumber = $aStoreNumbers[$singleNewStoreNumber];
            }
            return trim(implode(',', $aNewStoreNumbers), ',');
        }
        return trim(implode(',', $aStoreNumbers), ',');
    }

    /**
     * @param $validityMatch
     * @return string
     */
    private function getBrochureNumber($validityMatch)
    {
        $ret = str_replace(' ', '_', preg_replace('#[^a-z0-9 ]#i', '', $validityMatch[1]));
        $rets = explode('_', $ret);
        foreach ($rets as &$item) {
            if (end($rets) != $item) {
                $item = substr($item, 0, 3);
            }
        }
        return implode('_', $rets);
    }

    /**
     * @throws Exception
     */
    private function validateAndConvertDate(string $date): string
    {
        $pattern = '#\d{1,2}\.\d{1,2}\.\d{4}$#';
        if (preg_match($pattern, $date)) {
            // is valid
            return $date;
        }

        $pattern = '#(?<day>\d{1,2})\.(?<month>\d{1,2})(?<year>20\d{2})$#';
        if (!preg_match($pattern, $date, $dateMatch)) {
            throw new Exception('Was not possible to validate the date: ' . $date);
        }

        return $dateMatch['day'] . '.' . $dateMatch['month'] . '.' . $dateMatch['year'];
    }
}
