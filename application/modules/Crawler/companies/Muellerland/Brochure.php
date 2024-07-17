<?php

/*
 * Brochure Crawler für Müllerland (ID: 71441)
 */

class Crawler_Company_Muellerland_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();

        $cApiBrochures = $sApi->findActiveBrochuresByCompany($companyId);
        $aWeeklyBrochures = array();
        foreach ($cApiBrochures as $singleApiBrochure) {
            if (preg_match('#Wochen#', $singleApiBrochure['title']) && strtotime('+ 2 days') < strtotime($singleApiBrochure['validTo'])) {
                $this->_response->setIsImport(FALSE);
                $this->_response->setLoggingCode(4);

                return $this->_response;
            } else if (preg_match('#Wochen Angebote#', $singleApiBrochure['title'])) {
                $aWeeklyBrochures[] = $singleApiBrochure['brochureNumber'];
            }

        }

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $aLocalXlsFiles = array();
        foreach ($sFtp->listFiles() as $singleFile) {
            $pattern = '#_([^_]+?)_' . $sTimes->getWeeksYear() . '[^\.]*\.xlsx?#';
            if (preg_match($pattern, $singleFile)) {
                $aLocalXlsFiles[] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }
        
        $aBrochuresToCheck = array();
        foreach ($aLocalXlsFiles as $singleXlsFile) {
            $aData = $sExcel->readFile($singleXlsFile, TRUE)->getElement(0)->getData();
            foreach ($aData as $singleData) {
                if (is_null($singleData['Laufzeit']) || !strlen($singleData['Laufzeit'])) {
                    continue;
                }

                $pattern = '#(\d+)#';
                if (preg_match($pattern, $singleData['Streutermin'], $weekMatch)
                        && (int) $weekMatch[1] < (int) date('W')
                        ) {
                    continue;
                }

                $aBrochuresToCheck[preg_replace(array('#-#', '#\/#'), array('_', ''), $singleData['Anzeige/'])] = $singleData['Laufzeit'];
            }
        }
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aBrochuresToCheck as $pattern => $validity) {
            $aFilesToMerge = array();
            foreach ($sFtp->listFiles() as $singleFolder) {
                if (!preg_match('#' . $pattern . '#', $singleFolder)
                        || preg_match('#merged\.pdf#', $singleFolder)) {
                    continue;
                }
                $foundLinkFile = NULL;
                foreach ($sFtp->listFiles($singleFolder) as $singleFile) {
                    if (preg_match('#\.pdf$#', $singleFile)) {
                        $aFilesToMerge[] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                    }
                    if (preg_match('#(\.xlsx?|\.csv)$#', $singleFile)) {
                        $this->_logger->info($companyId . ': ' . $singleFolder . ' - link file found.');
                        $foundLinkFile = $singleFolder;
                    }
                }

                sort($aFilesToMerge);

                $mergedFilePath = $sPdf->merge($aFilesToMerge, $localPath);
                
                exec('mv ' . $mergedFilePath . ' ' . $localPath . $singleFolder . '.pdf');
                
                $mergedFilePath = preg_replace('#\/([^\/\.]+?)\.pdf#', '/' . $singleFolder . '.pdf', $mergedFilePath);

                if ($foundLinkFile) {
                    $sFtp->upload($mergedFilePath, $singleFolder . '_merged.pdf');
                    
                    $sRedmine = new Marktjagd_Service_Output_Redmine();
                    $eTask = new Marktjagd_Database_Entity_Task;
                    
                    $eTask->setIdCompany($companyId)
                            ->setTitle('Prospekt ' . $singleFolder . ' verlinken')
                            ->setNextDate(date('d.m.Y'));
                    
                    $sRedmine->generateTicketByCompany($eTask, '');

                    continue;
                }

                $patternValidity = '#(\d{2}\.)(\d{2}\.)?(\d{2,4})?\s*-\s*(\d{2}\.)(\d{2}\.)(\d{2,4})#';
                if (!preg_match($patternValidity, $validity, $validityMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure validity - ' . $validity);
                    continue;
                }

                $strValidStart = $validityMatch[1] . $validityMatch[2];
                if (!strlen($validityMatch[3])) {
                    $strValidStart .= str_pad($validityMatch[6], 4, '20', STR_PAD_LEFT);
                } else {
                    $strValidStart .= str_pad($validityMatch[3], 4, '20', STR_PAD_LEFT);
                }
                
                if (!strlen($validityMatch[2])) {
                    $strValidStart = $validityMatch[1] . $validityMatch[5] . str_pad($validityMatch[6], 4, '20', STR_PAD_LEFT);
                }

                $strValidEnd = $validityMatch[4] . $validityMatch[5] . str_pad($validityMatch[6], 4, '20', STR_PAD_LEFT);

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setBrochureNumber($singleFolder)
                        ->setUrl($sFtp->generatePublicFtpUrl($mergedFilePath))
                        ->setTitle('Wochen Angebote')
                        ->setStart($strValidStart)
                        ->setEnd($strValidEnd)
                        ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 2 days')))
                        ->setVariety('leaflet');

                if (!in_array($singleFolder, $aWeeklyBrochures)) {
                    $cBrochures->addElement($eBrochure);
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
