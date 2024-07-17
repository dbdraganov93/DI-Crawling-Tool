<?php

/**
 * Brochure Crawler für Kienast (ID: 29190, 29191)
 */
class Crawler_Company_Kienast_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $ftpFolderName = '29191';
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $aCompanies = array(
            '29190' => '#abc#i',
            '29191' => '#k\s*\+\s*k#i'
        );

        $sFtp->connect($ftpFolderName);
        $aFiles = $sFtp->listFiles();
        $pattern = '#Werbefilialen\.xls$#';
        foreach ($aFiles as $singleFile) {
            if (preg_match($pattern, $singleFile)) {
                $localFileName = $sFtp->downloadFtpToCompanyDir($singleFile, $companyId);
            }
        }
        $aWorksheets = $sExcel->readFile($localFileName, true)->getElements();
        foreach ($aWorksheets as $singleWorksheet) {
            if (preg_match($aCompanies[$companyId], $singleWorksheet->getTitle())) {
                $strStoreNumbers = array();
                foreach ($singleWorksheet->getData() as $singleStore) {
                    $strStoreNumbers[] = $singleStore['Nr.'];
                }
                break;
            }
        }
        $pattern = '#Filialen_gesamt\.xls$#';
        foreach ($aFiles as $singleFile) {
            if (preg_match($pattern, $singleFile)) {
                $localFileName = $sFtp->downloadFtpToCompanyDir($singleFile, $companyId);
            }
        }
        $aWorksheets = $sExcel->readFile($localFileName, true)->getElements();
        foreach ($aWorksheets as $singleWorksheet) {
            if (preg_match($aCompanies[$companyId], $singleWorksheet->getTitle())) {
                $strStoreNumbersAll = array();
                foreach ($singleWorksheet->getData() as $singleStore) {
 
                    $strStoreNumbersAll[] = $singleStore['Nr.'];
                }
                break;
            }
        }
        foreach ($strStoreNumbersAll as $singleNumber) {
            if (!in_array($singleNumber, $strStoreNumbers)) {
                Zend_Debug::dump($singleNumber);
            }
        }
        die;
    }

}
