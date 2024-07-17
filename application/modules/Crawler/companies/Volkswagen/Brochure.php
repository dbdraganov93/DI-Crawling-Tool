<?php
/**
 * Brochure Crawler für Volkswagen (ID: 72424)
 */

class Crawler_Company_Volkswagen_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $localPath = $sFtp->connect($companyId, TRUE);

        $localBrochures = [];
        $clickoutFile = '';
        $assignmentFile = '';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#_(\d+)\.pdf#', $singleFile, $brochureNumberMatch)) {
                $localBrochures[$brochureNumberMatch[1]] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }

            if (preg_match('#clickout\.csv#', $singleFile)) {
                $clickoutFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }

            if (preg_match('#assignment1\.csv#', $singleFile)) {
                $assignmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($assignmentFile, TRUE, ',')->getElement(0)->getData();

        $aAssignment = [];
        foreach ($aData as $singleRow) {
            if (strlen($singleRow['Betriebsnummer'])) {
                $strBetriebsnummer = $singleRow['Betriebsnummer'];
                $aAssignment[$strBetriebsnummer]['brochureId'] = $singleRow['BrochureID'];
            }
            $aAssignment[$strBetriebsnummer]['zipcodes'][] = $singleRow['PLZ 5-stellig'];
        }

        $aData = $sPss->readFile($clickoutFile, TRUE, ';')->getElement(0)->getData();

        $aClickOuts = [];
        foreach ($aData as $singleRow) {
            $aClickOuts[$singleRow['Betriebsnummer']] = $singleRow['PIA Ziel-URL + Trackingcode'];
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aAssignment as $key => $singleBrochure) {
            if (is_null($singleBrochure['brochureId'])) {
                continue;
            }
            $aClickOutsToSet = [
                [
                    'page' => 0,
                    'startX' => 260,
                    'startY' => 240,
                    'endX' => 290,
                    'endY' => 270,
                    'link' => $aClickOuts[$key]
                ],
                [
                    'page' => 1,
                    'startX' => 320,
                    'startY' => 70,
                    'endX' => 350,
                    'endY' => 100,
                    'link' => $aClickOuts[$key]
                ]
            ];

            $jsonFile = APPLICATION_PATH . '/../public/files/template_' . date('dmYHimsu') . '.json';
            $fh = fopen($jsonFile, 'w');
            fwrite($fh, json_encode($aClickOutsToSet));
            fclose($fh);

            $filePath = $sPdf->setAnnotations($localBrochures[$singleBrochure['brochureId']], $jsonFile);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setBrochureNumber($key)
                ->setTitle('SUV WEEKS - Bereit für die City')
                ->setUrl($filePath)
                ->setZipCode(implode(',', $singleBrochure['zipcodes']))
                ->setStart('01.08.2022')
                ->setEnd('04.09.2022')
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }

}