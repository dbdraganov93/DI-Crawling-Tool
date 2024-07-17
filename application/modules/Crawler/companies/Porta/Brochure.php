<?php

/**
 * Brochure Crawler für Porta (ID: 108)
 */
class Crawler_Company_Porta_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sMjFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPhpExcel = new Marktjagd_Service_Input_PhpExcel();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sArchive = new Marktjagd_Service_Input_Archive();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sMjFtp->connect($companyId);
        $localPath = $sMjFtp->generateLocalDownloadFolder($companyId);
        $aFiles = $sMjFtp->listFiles();

        $excelFiles = array();
        $zipFiles = array();
        $pdfFiles = array();

        foreach ($aFiles as $singleFile) {
            if (preg_match('#\.zip$#', $singleFile)) {
                $zipFiles[] = $singleFile;
            }
        }

        foreach ($zipFiles as $zipFile) {
            $this->_logger->info('found and open ' . $zipFile);
            $localZipFile = $sMjFtp->downloadFtpToDir($zipFile, $localPath);

            $this->_logger->info('unzip ' . $localZipFile . ' to ' . $localPath);
            $sArchive->unzip($localZipFile, $localPath);
        }

        $dirIterator = new RecursiveDirectoryIterator($localPath);
        foreach (new RecursiveIteratorIterator($dirIterator) as $file) {
            if (preg_match('#\.xlsx?$#', $file)) {
                $excelFiles[] = $file;
            }
            if (preg_match('#__MACOSX#', $file)) {
                continue;
            }

            if (preg_match('#\.pdf$#is', $file)) {
                $pdfFiles[] = (string)$file;
            }
        }

        foreach ($excelFiles as $excelFile) {
            $this->_logger->info('found and open ' . $excelFile);

            $aWorksheets = $sPhpExcel->readFile($excelFile, true);
            $excelData = $aWorksheets->getElement(0)->getData();

            foreach ($excelData as $excelLine) {
                if (array_key_exists('Dateiname', $excelLine) && strlen($excelLine['Dateiname'])) {
                    if (!preg_match('#\.pdf$#is', $excelLine['Dateiname'])) {
                        $this->_logger->warn('invalid pdf filename, skip ' . $excelLine['Dateiname']);
                        continue;
                    }
                    if (!file_exists($localPath.'/'.$excelLine['Dateiname'])) {
                        $this->_logger->warn('filename ' . $excelLine['Dateiname'] . ' not found in local path, skipping');
                        continue;
                    }

                    $eBrochure = new Marktjagd_Entity_Api_Brochure();

                    $eBrochure->setVariety('leaflet')
                        ->setTitle('Möbel Angebote')
                        ->setStoreNumber(implode(',', preg_split('#\s*,\s*#', $excelLine['Einrichtungshaus'])));

                    foreach ($pdfFiles as $pdfFile) {
                        if (preg_match('#(' . $excelLine['Dateiname'] . ')$#is', $pdfFile)) {
                            $eBrochure->setUrl($sMjFtp->generatePublicFtpUrl($pdfFile))
                                ->setBrochureNumber(substr(preg_replace('#\.pdf#is', '', $excelLine['Dateiname']), 0, 31));
                        }
                    }

                    $valid = preg_split('#\s*-\s*#', $excelLine['Laufzeit']);

                    $startToken = preg_split('#\s*\.\s*#', $valid[0]);
                    $endToken = preg_split('#\s*\.\s*#', $valid[1]);

                    if (count($endToken) == 2 || !strlen(trim($endToken[2]))) {
                        $endToken[2] = $sTimes->getWeeksYear();
                    }

                    if (count($startToken) == 2 || !strlen(trim($startToken[2]))) {
                        $startToken[2] = $endToken[2];
                    }

                    $eBrochure->setStart(implode('.', $startToken))
                        ->setEnd(implode('.', $endToken))
                        ->setVisibleStart($eBrochure->getStart());
                    if (preg_match('#MWO\s*ohne#', $eBrochure->getStoreNumber())) {
                        continue;
                    }
                    $cBrochures->addElement($eBrochure);
                } else {
                    $this->_logger->info('empty Excel line, skip ' . Zend_Debug::dump($excelLine, NULL, true));
                }
            }
        }

        $sMjFtp->transformCollection($cBrochures, '/' . $companyId . '/', 'brochures', $localPath);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
