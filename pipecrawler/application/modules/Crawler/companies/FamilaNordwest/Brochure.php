<?php

/* 
 * Brochure Crawler für Famila Nordwest (ID: 29025)
 */

class Crawler_Company_FamilaNordwest_Brochure extends Crawler_Generic_Company
{
    public const BROCHURE_NUMBER_MAX_CHARS = 32;

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $week = 'this';
        $assignmentFileFound = false;

        $localPath = $sFtp->connect(29026, TRUE);

        $localSurveyFile = FALSE;
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#famila[^\.]*' . date('Y', strtotime($week . ' week')) . '_KW[^\.]*'
                . date('W', strtotime($week . ' week')) . '\.xlsx#', $singleFile)) {
                $this->_logger->info('Downloading XLS: ' . $singleFile);
                $localAssignmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $assignmentFileFound = true;

                continue;
            }
            if (preg_match('#umfrage_famila\.pdf#', $singleFile)) {
                $this->_logger->info('Downloading Survey PDF: ' . $singleFile);
                $localSurveyFile = TRUE;
            }
        }
        $week = 'next';
        $aLocalBrochures = [];
        foreach ($sFtp->listFiles('./famila/KW' . date('W', strtotime($week . ' week'))) as $singleFile) {
            if (preg_match('#([^\.\/]+)\.pdf$#', $singleFile, $nameMatch)) {
                $this->_logger->info('Downloading PDF: ' . $singleFile);
                $aLocalBrochures[$nameMatch[1]] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();
        $this->_logger->info('Downloading done!');

        $aCleanedAssignment = [];
        $aHeader = [];
        if($assignmentFileFound) {
            $aData = $sPss->readFile($localAssignmentFile)->getElement(0)->getData();

            foreach ($aData as $singleRow) {
                if (!$singleRow[8]) {
                    continue;
                }
                if (!count($aHeader)) {
                    $aHeader = $singleRow;
                    continue;
                }

                $aAssignmentData = array_combine($aHeader, $singleRow);
                $aAssignmentData['Version'] = preg_replace(['#\+Einleger\s+#', '#Wechselversion\s*#', '#normal#'], ['-EL', '', 'alle'], $aAssignmentData['Version']);
                if (preg_match_all('#\((\d+)\)#', $aAssignmentData['Unterausgabe'], $storeNumberMatches)) {
                    if (!$aCleanedAssignment[$aAssignmentData['Version']]) {
                        $aCleanedAssignment[$aAssignmentData['Version']] = $storeNumberMatches[1];
                    } else {
                        $aCleanedAssignment[$aAssignmentData['Version']] = array_merge($aCleanedAssignment[$aAssignmentData['Version']], $storeNumberMatches[1]);
                    }
                }
            }
        } else {
            $this->_logger->warn('No XLS assignment file found!');

            foreach ($aLocalBrochures as $name => $path) {
                $aCleanedAssignment[$path] = $name;
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aCleanedAssignment as $assignment => $stores) {
            $brochureToAssign = preg_grep('#' . $assignment . '#', $aLocalBrochures);
            if (!$brochureToAssign) {
                $this->_logger->info($companyId . ': no brochure assigned for ' . $assignment);
                continue;
            }

            $localPath = array_values($brochureToAssign)[0];
            if ($localSurveyFile) {
                $localPath = $sPdf->implementSurvey($localPath, 3);
            }

            if(!$assignmentFileFound) {
                $brochureName = $stores;
                if(strlen($brochureName) > self::BROCHURE_NUMBER_MAX_CHARS) {
                    $charactersToDelete = strlen($stores) - self::BROCHURE_NUMBER_MAX_CHARS;
                    $brochureName =  substr($stores, 0, - $charactersToDelete);
                }

                $brochureNumber = $brochureName;
            } else {
                $brochureNumber = $assignment . '_KW'
                    . date('W', strtotime($week . ' week')) . '_'
                    . date('Y', strtotime($week . ' week'));
            }

            if(preg_match('#Einleger#', $assignment)) {
                $title = 'Famila: Einleger';
            } else {
                $title = 'Famila: Wochenangebote';
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($title)
                ->setBrochureNumber($brochureNumber)
                ->setUrl($localPath)
                ->setStart(date('d.m.Y', strtotime('monday ' . $week . ' week')))
                ->setEnd(date('d.m.Y', strtotime('saturday ' . $week . ' week')))
                ->setVisibleStart($eBrochure->getStart())
                ->setStoreNumber(empty($stores) ? '' : implode(',', $stores))
                ->setVariety('leaflet')
            ;

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }
}
