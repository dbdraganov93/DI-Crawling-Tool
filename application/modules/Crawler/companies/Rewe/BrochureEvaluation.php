<?php

/**
 * Brochure evaluation script for REWE (ID: 23)
 */

class Crawler_Company_Rewe_BrochureEvaluation extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $week = 'last';

        $localPath = $sFtp->connect($companyId, TRUE);

        $evaluationFile = '';
        $checkFile = '';
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#auswertung[^\.]*KW' . date('W', strtotime($week . ' week')) . date('y', strtotime($week . ' week')) . '\.csv#', $singleRemoteFile)) {
                $evaluationFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }

            if (preg_match('#brochures_CW' . date('W', strtotime($week . ' week')) . '\.csv#', $singleRemoteFile)) {
                $checkFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        $sFtp->close();

        $aShortenedBrochureNumbers = [];
        $aData = $sPss->readFile($checkFile, TRUE, ';')->getElement(0)->getData();
        foreach ($aData as $singleRow) {
            $aShortenedBrochureNumbers[$singleRow['cut']] = $singleRow['original'];
        }

        $fh = fopen($evaluationFile, 'r');
        $aData = [];
        $aHeader = [];
        while (($singleRow = (fgetcsv($fh, 0, ','))) != FALSE) {
            if (!count($aHeader)) {
                $aHeader = $singleRow;
                continue;
            }
            $aData[] = array_combine($aHeader, $singleRow);
        }
        fclose($fh);

        foreach ($aData as &$singleRow) {
            if (array_key_exists($singleRow['Prospektnummer'], $aShortenedBrochureNumbers)) {
                $singleRow['Prospektnummer'] = $aShortenedBrochureNumbers[$singleRow['Prospektnummer']];
            }
            $singleRow['Prospektnummer'] = preg_replace('#^' . date('W', strtotime($week . ' week')) . date('y', strtotime($week . ' week')) . '#', '', $singleRow['Prospektnummer']);
        }

        $newFileName = preg_replace('#([.⁺]?)\.csv#', '$1_replaced.csv', $evaluationFile);
        $fh = fopen($newFileName, 'w+');
        $count = 0;

        foreach ($aData as $singleRow) {
            if ($count++ == 0) {
                fputcsv($fh, array_keys($singleRow), ';');
            }
            fputcsv($fh, $singleRow, ';');
        }
        fclose($fh);

        $sFtp->connect($companyId);
        if ($sFtp->upload($newFileName, basename($newFileName))) {
            $this->_logger->info($companyId . ': upload of file ' . basename($newFileName) . ' successful.');
        }
        $sFtp->close();

        $this->_response->setLoggingCode(4)
            ->setIsImport(FALSE);

        return $this->_response;
    }
}