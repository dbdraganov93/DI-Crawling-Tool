<?php

/*
 * Brochure Crawler für Combi (ID: 28832)
 */

class Crawler_Company_Combi_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        ini_set('memory_limit', '4G');
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $week = 'next';

        $localPath = $sFtp->connect(29026, TRUE);

        $localSurveyFile = FALSE;
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Combi_KW[^\.]*'
                . date('W', strtotime($week . ' week')) . '\.xls#', $singleFile)) {
                $localAssignmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $this->_logger->info('Assignment file found: ' . $localAssignmentFile);
                continue;
            }
            if (preg_match('#umfrage_combi\.pdf#', $singleFile)) {
                $localSurveyFile = TRUE;
            }
        }

        $aLocalBrochures = [];
        foreach ($sFtp->listFiles('./Combi/KW' . date('W', strtotime($week . ' week'))) as $singleFile) {
            if (preg_match('#([^\.\/]+)\.pdf$#', $singleFile, $nameMatch)) {
                $this->_logger->info('Downloading PDF from FTP: ' . $singleFile);
                $aLocalBrochures[$nameMatch[1]] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
            if (preg_match('#combi_distribution.xlsx$#', $singleFile, $nameMatch)) {
                $this->_logger->info('Found the Special Distribution/Assignment List: ' . $singleFile);
                $specialAssigmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();

        $aCleanedBrochures = [];
        foreach ($aLocalBrochures as $key => $localPath) {
            $aKeys = preg_split('#[-|_]#', $key);
            $append = '';
            $end = count($aKeys);
            if (preg_match('#ELNF#', end($aKeys))) {
                $append = '-ELNF';
                $end = count($aKeys) - 1;
            }
            for ($i = 0; $i < $end; $i++) {
                $aCleanedBrochures[$localPath][] = $aKeys[$i] . $append;
            }
        }

        $aData = $sPss->readFile($localAssignmentFile, TRUE)->getElement(0)->getData();

        $aCleanedAssignment = [];
        foreach ($aData as $key => $singleRow) {
            if($key == 0 || empty($singleRow['Version Einleger'])) {
                continue;
            }

            foreach ($singleRow as $cellName => $cellValue) {
                if ($cellName == 'Unterausgabe' && !is_null($cellValue)) {
                    if(isset($aCleanedAssignment[$singleRow['Version Einleger']]) &&
                        in_array($cellValue, $aCleanedAssignment[$singleRow['Version Einleger']])
                    ) {
                        continue;
                    }

                    $pattern = '#(?<storeNumber>\d{6})#';
                    if(!preg_match_all($pattern, $cellValue, $storesMatch)) {
                        $this->_logger->warn('Crawler is not able to get stores form: ' . $cellValue);

                        continue;
                    }

                    foreach ($storesMatch['storeNumber'] as $storeNumber) {
                        $aCleanedAssignment[$singleRow['Version Einleger']][] = $storeNumber;
                    }
                }
            }
        }

        $specialAssigmentData = $sPss->readFile($specialAssigmentFile, true)->getElement(0)->getData();
        $aSpecialAssignment = [];
        foreach ($specialAssigmentData as $specialAssigmentLine) {
            $distributionList = $specialAssigmentLine['gültig für'];

            if (preg_match('#\w\s*-\s*\w#', $specialAssigmentLine['gültig für'])) {
                $distributionList = explode('-', $specialAssigmentLine['gültig für']);
            }

            $aSpecialAssignment[$specialAssigmentLine['gelieferte']] = $distributionList;
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aCleanedBrochures as $localPath => $aStoreAreas) {
            $aStores = [];
            foreach ($aStoreAreas as $singleStoreArea) {
                // checks if it has a Special Assignment
                if (array_key_exists($singleStoreArea, $aSpecialAssignment) && is_array($aSpecialAssignment[$singleStoreArea])) {
                    foreach ($aSpecialAssignment[$singleStoreArea] as $specialAssigment) {
                        if (empty($aStores)) {
                            $aStores = $aCleanedAssignment[$specialAssigment];
                        } else {
                            $aStores = array_merge($aStores, $aCleanedAssignment[$specialAssigment]);
                        }
                    }

                    continue;
                }

                // no special assignment file
                if (empty($aStores)) {
                    $aStores = $aCleanedAssignment[$singleStoreArea];
                } else {
                    $aStores = array_merge($aStores, $aCleanedAssignment[$singleStoreArea]);
                }
            }

            if(empty($aStores)) {
                $this->_logger->warn('No stores found for: ' . $localPath);
                continue;
            }

            if ($localSurveyFile) {
                $localPath = $sPdf->implementSurvey($localPath, 3);
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Unsere Combi Wochenangebote')
                ->setBrochureNumber($aStoreAreas[1] . '_' .
                    $aStoreAreas[2]  . '_' .
                    date('Y', strtotime($week . ' week')))
                ->setUrl($localPath)
                ->setStart(date('d.m.Y', strtotime('monday ' . $week . ' week')))
                ->setEnd(date('d.m.Y', strtotime('saturday ' . $week . ' week')))
                ->setVisibleStart($eBrochure->getStart())
                ->setStoreNumber(implode(',', $aStores));

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }

}
