<?php

/*
 * Brochure Crawler für Möbel Hardeck (ID: 69067)
 */

class Crawler_Company_Hardeck_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sFtp->connect($companyId);

        $aStoreNumbers = array(
            'Bo' => 'Bo,HardiBo',
            'Hil?' => 'Hil',
            'Bra?' => 'Bra',
            'Sen?' => 'Sen'
        );

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $aFiles = array();

        foreach ($sFtp->listFiles() as $singleFolder) {
            if (preg_match('#Archiv#', $singleFolder)) {
                continue;
            }
            if (preg_match('#(' . $sTimes->getWeekNr() . '|' . $sTimes->getWeekNr('next') . ')#', $singleFolder)) {
                $sFtp->changedir($singleFolder);
                foreach ($sFtp->listFiles() as $singleFile) {
                    if (preg_match('#\.pdf#', $singleFile)) {
                        $aFiles[] = $sFtp->downloadFtpToDir('/' . $companyId . '/' . $singleFolder . '/' . $singleFile, $localPath);
                    }
                }
                $sFtp->changedir('../');
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aFiles as $singleFile) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $strStoreNumbers = '';
            foreach ($aStoreNumbers as $singleStoreNumberKey => $singleStoreNumberKeyValue) {
                if (preg_match('#' . $singleStoreNumberKey . '#i', $singleFile)) {
                    if (strlen($strStoreNumbers)) {
                        $strStoreNumbers .= ',';
                    }
                    $strStoreNumbers .= $singleStoreNumberKeyValue;
                }
            }

            $eBrochure->setTitle('Fachsortimentsprospekt');

            $patternWeek = '#\-([0-9]{1,2})\_([0-9]{4})\.pdf$#';

            if (!preg_match('#FaSo#', $singleFile)) {
                $eBrochure->setTitle('Wochen Angebote');

                $patternWeek = '#(([0-9]{4})\-KW([0-9]{1,2})|([0-9]{1,2})KW[^\.]+?)\.pdf$#';
            }

            if (!preg_match($patternWeek, $singleFile, $dateMatch)) {
                throw new Exception($companyId . ': unable to get date for: ' . $singleFile);
            }

            if (count($dateMatch) == 4) {
                $week = $dateMatch[3];
                $year = $dateMatch[2];
            }
            else {
                $week = $dateMatch[4];
                $year = $sTimes->getWeeksYear();
            }


            if ((int) $week < (int) date('W') && (int) $year == (int) $sTimes->getWeeksYear()) {
                continue;
            }

            $eBrochure->setStart($sTimes->findDateForWeekday($year, $week, 'Mi'))
                    ->setEnd(date('d.m.Y', strtotime($eBrochure->getStart() . '+13days')));

            if (!preg_match('#FaSo#', $singleFile)) {
                $eBrochure->setStart($sTimes->findDateForWeekday($year, $week, 'Mi'))
                        ->setEnd(date('d.m.Y', strtotime($eBrochure->getStart() . '+6days')));
            }

            $eBrochure->setUrl(preg_replace('#(.+?)(/files.+?)#', 'https://di-gui.marktjagd.de$2', $singleFile))
                    ->setStoreNumber($strStoreNumbers)
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet');

            if (preg_match('#Bo#', $eBrochure->getStoreNumber())) {
                $eBrochure->setStoreNumber('Bo, HardiBo');
            }

            $eBrochure->setBrochureNumber($week . '_' . preg_replace('#[^\w]#', '', $eBrochure->getStoreNumber()));
            
            $cBrochures->addElement($eBrochure);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
