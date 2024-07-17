<?php

/*
 * Brochure Crawler für REWE (ID: 23)
 */

class Crawler_Company_Rewe_Brochure extends Crawler_Generic_Company
{
    /**
     * @throws Exception
     */
    public function crawl($companyId)
    {
        if (is_dir(APPLICATION_PATH . '/../public/files/ftp/23')) {
            exec('rm -r ' . APPLICATION_PATH . '/../public/files/ftp/23');
        }
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sArchive = new Marktjagd_Service_Input_Archive();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $week = 'next';

        # OPTIONAL: set tracking bug manually
        $trackingId = '56185614';  #e.g.: '44242388'//55526647

        #this is only for the special budget
        $extraBudgetStoreList = [
            '45655788',
            '45400855',
            '45655795',
            '45400282',
            '45400300',
            '45400302',
            '45400309',
            '45400344',
            '45400346',
            '45400374',
            '45400375',
            '45400376',
            '45400378',
            '45400391',
            '45400400',
            '45400401',
            '45655907',
            '45655644',
            '45400258',
            '45655645',
            '45400290',
            '45400299',
            '45655632',
            '45655806',
            '45400292',
            '45655613',
            '45655602',
            '45400245',
            '42655121',
            '42655436',
            '42655030',
            '42655026',
            '42655058',
            '42655698',
            '42655005',
            '42655264',
            '42655578',
            '42400058',
            '42655812',
            '42655176',
            '42655151',
            '42655527',
            '42655528',
            '42655781',
            '42655594',
            '42655632',
            '42655665',
            '41402384',
            '41402148',
            '41402202',
            '41402157',
            '41402201',
            '41402203',
            '41402153',
            '41402195',
            '41402189',
            '41402810',
            '41402803',
            '41400666',
            '41400718',
            '41400468',
            '41400873',
            '41400994',
            '41654505',
            '41654050',
            '41400174',
            '41656660',
            '41651980',
            '42655690',
            '42655716',
            '42655778',
            '42655762',
        ];
        $extraBudgetTrackingId = 'https://track.adform.net/adfserve/?bn=56210237;1x1inv=1;srctype=3;gdpr=${gdpr};gdpr_consent=${gdpr_consent_50};ord=';

        $saarlandBudgetStoreList = [
            '45655532',
            '45655549',
            '45655640',
            '45400156',
            '45400159',
            '45400160',
            '45400153',
            '45655500',
            '45655857',
            '45655861',
            '45400755',
            '45400756',
            '45400757',
            '45400763',
            '45400765',
            '45400768',
            '45400769',
            '45400772',
            '45400774',
            '45400771',
            '45400760',
            '45400761',
            '45400254',
            '45400259',
            '45400289',
            '45400296',
            '45400268',
            '45400418',
            '45400423',
            '45655812',
            '45400427',
            '45655658',
            '45400421',
            '45400431',
            '45400434',
            '45655892',
            '45400453',
            '45655642',
            '45400468',
            '45655867',
            '45655463'
        ];
        $saarlandBudgetTrackingId = 'https://track.adform.net/adfserve/?bn=56210238;1x1inv=1;srctype=3;gdpr=${gdpr};gdpr_consent=${gdpr_consent_50};ord=';

        $cStoresApi = $sApi->findStoresByCompany($companyId)->getElements();

        $aStoreNumbers = [];
        foreach ($cStoresApi as $eStoreApi) {
            $aStoreNumbers[$eStoreApi->getStoreNumber()] = [];
        }

        $localPath = $sFtp->connect($companyId, TRUE);
        $localAssignmentFile = '';
        $strLastPage = '';
        $aLeafletArchives = [];
        $aSetupFiles = [];

        # find the right folder
        foreach ($sFtp->listFiles() as $singleFolder) {
            if (!preg_match('#\.txt$#', $singleFolder) && preg_match('#KW[\s|_]*' . date('W', strtotime($week . ' week')) . '#', $singleFolder, $dateMatch)) {
                $folderToCheck = './' . $singleFolder;
                $this->_logger->info('Found folder ' . $folderToCheck);
                break;
            }
        }

        # find the tracking bug file (if provided)
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.txt$#', $singleFile) && preg_match('#KW[\s|_]*' . date('W', strtotime($week . ' week')) . '#', $singleFile, $dateMatch)) {
                $trackingBugFile = $sFtp->downloadFtpToDir($singleFile, $localPath);;
                $this->_logger->info($companyId . ': found file with tracking url ' . $trackingBugFile);
                break;
            }
        }

        if($trackingBugFile) {
            # set the tracking bug read from file
            $trackingBugContent = file_get_contents($trackingBugFile);
            if (preg_match_all('#\?bn=(\d{1,})+#',$trackingBugContent, $matches)) {
                $this->_logger->info($companyId . ': tracking id found.');
            }
            $trackingId = $matches[1][0];
            $trackingId = $trackingId == ''? $matches[1][0] : $trackingId;
            unset($matches,$trackingBugContent, $trackingBugFile);
        }

        # extend the maximum number of open files at the same time (the limit caused errors in the past)
        shell_exec("ulimit -n 65536");

        foreach ($sFtp->listFiles($folderToCheck) as $singleFile) {
            if (preg_match('#Marktliste[\s|_]*KW\s?' . date('W', strtotime($week . ' week')) . '#', $singleFile)) {
                $this->_logger->info($companyId . ': found assignment file.');
                $localAssignmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }

            if (preg_match('#Druckplan_[^\.]*' . date('W', strtotime($week . ' week')) . '[^\.]*\.xlsx?#', $singleFile)) {
                $this->_logger->info($companyId . ': found setup file.');
                $aSetupFiles[] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }

            if (preg_match('#' . date('W', strtotime($week . ' week')) . '[^\.]*\.zip#', $singleFile)) {
                $aLeafletArchives[] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }

            if (preg_match('#app_KW' . date('W', strtotime($week . ' week')) . '#', $singleFile)) {
                $strLastPage = $sFtp->downloadFtpToDir($singleFile, $localPath);

                $aCoordsToLink[] = [
                    'page' => 0,
                    'height' => 841.89,
                    'width' => 595.276,
                    'startX' => 171.492,
                    'endX' => 209.455,
                    'startY' => 191.923,
                    'endY' => 233.815,
                    'link' => 'https://app.adjust.com/jsr?url=https%3A%2F%2Fj89v.adj.st%2Fcoupons%3Fadj_t%3Dujubnj0_qi0perr%26adj_deep_link%3Drewe%253A%252F%252Fcoupons%26adj_fallback%3Dhttps%253A%252F%252Fwww.rewe.de%252Fservice%252Fapp-couponing%252F%26adj_redirect_macos%3Dhttps%253A%252F%252Fwww.rewe.de%252Fservice%252Fapp-couponing%252F%26adj_label%3DAFF'
                ];
                $coordFileName = $localPath . 'coordinates_' . $companyId . '_co.json';

                $fh = fopen($coordFileName, 'w+');
                fwrite($fh, json_encode($aCoordsToLink));
                fclose($fh);

                $strLastPage = $sPdf->setAnnotations($strLastPage, $coordFileName);
            }
        }


        if (!strlen($strLastPage)) {
            $strLastPage = $sFtp->downloadFtpToDir('/23/digitale_zusatzseite_app_fallback.pdf', $localPath);
        }
        $sFtp->close();

        if (!strlen($localAssignmentFile)) {
            throw new Exception($companyId . ': unable to find assignment file.');
        }

        $aPdfFiles = [];
        foreach ($aLeafletArchives as $singleArchive) {
            if ($sArchive->unzip($singleArchive, $localPath)) {
                unlink($singleArchive);
            }
        }
        foreach (scandir($localPath) as $singleFolder)
        {
            $pattern = '#:#';
            if (preg_match($pattern, $singleFolder))
            {
                $newName = preg_replace('#:#', '_', $singleFolder);
                rename($localPath . $singleFolder,$localPath .  $newName);
                echo $singleFolder;
            }
        }

        foreach (scandir($localPath) as $singleFolder) {
            $this->_logger->info('found folder:' . $singleFolder);
            if (!preg_match('#^(\.|_)#', $singleFolder) && !preg_match('#\.(zip|xlsx?|pdf|json)$#', $singleFolder)) {
                foreach (scandir($localPath . $singleFolder) as $singleFile) {
                    if (preg_match('#\.pdf#', $singleFile)) {
                        $aReplace = ['#[-_]pdf-\d#', '#(^\w)#'];
                        if (preg_match('#^PETZ#', $singleFolder)) {
                            $aReplace[] = '#_SNP_1#';
                            $aPdfFiles['P' . preg_replace($aReplace, '', pathinfo($singleFile)['filename'])] = $localPath . $singleFolder . '/' . $singleFile;
                        } else {
                            $aPdfFiles['R' . preg_replace($aReplace, '', pathinfo($singleFile)['filename'])] = $localPath . $singleFolder . '/' . $singleFile;
                        }
                    }
                }
            }
        }

        # for Debugging purposes, we write the PDF array in a file
        $pdfDebugFileName = $localPath . 'PDF_DEBUG.json';
        $fh = fopen($pdfDebugFileName, 'w+');
        fwrite($fh, json_encode($aPdfFiles));
        fclose($fh);


        $aData = $this->getDataFromLocalAssignmentFile($localAssignmentFile, $sPss);
        $aBrochures = [];
        foreach ($aData as $singleRow) {
            if (array_key_exists($singleRow['RWS Markt WAWI-Marktnummer'], $aStoreNumbers)) {
                $aBrochures[$singleRow['RWS Best Angebot Version Kurz ID']]['stores'][] = $singleRow['RWS Markt WAWI-Marktnummer'];
            }
        }

        foreach ($aSetupFiles as $singleSetupFile) {
            $aData = $sPss->readFile($singleSetupFile, TRUE)->getElement(0)->getData();

            foreach ($aData as $singleRow) {
                if (!array_key_exists($singleRow['HZ-Version'], $aBrochures)) {
                    continue;
                }
                foreach ($singleRow as $key => $value) {
                    if (!preg_match('#Seite\s*(\d+)#', $key, $siteNoMatch)
                        || (preg_match('#Seite#', $key) && !strlen($value))) {
                        continue;
                    }
                    if (array_key_exists($value, $aPdfFiles)) {
                        $aBrochures[$singleRow['HZ-Version']]['sites'][$siteNoMatch[1] - 1] = $aPdfFiles[$value];
                    }
                    else {
                        $this->_logger->warn('PDF not found: ' . $value);
                    }
                }
            }
        }

        # for Debugging purposes, we write the brochures array in a file
        $brochureDebugFileName = $localPath . 'aBrochures_DEBUG.json';
        $fh = fopen($brochureDebugFileName, 'w+');
        fwrite($fh, json_encode($aBrochures));
        fclose($fh);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aBrochures as $brochureNumber => $aInfos) {
            array_push($aInfos['sites'], $strLastPage);
            $brochurePath = $sPdf->merge($aInfos['sites'], $localPath);

            # this is only for the extra budget until KW 52
            $extraBudgetStores = [];
            foreach($aInfos['stores'] as $index => $storeNumber) {
                if(in_array($storeNumber, $extraBudgetStoreList) && $week <= 52) {
                    $extraBudgetStores[] = $storeNumber;
                    unset($aInfos['stores'][$index]);
                }
            }
            # extra Budget until KW52
            if(!empty($extraBudgetStores)) {
                $eBrochure2 = new Marktjagd_Entity_Api_Brochure();
                copy($brochurePath, preg_replace('#.pdf#', '_2.pdf', $brochurePath));
                $eBrochure2->setTitle('REWE: Wochenangebote')
                    ->setUrl(preg_replace('#.pdf#', '_2.pdf', $brochurePath))
                    ->setBrochureNumber(substr("SI_" . $brochureNumber, 0, 27) . '_' . date('W', strtotime($week . ' week')) . date('y', strtotime($week . ' week')))
                    ->setStart(date('d.m.Y', strtotime('monday ' . $week . ' week')))
                    ->setEnd(date('d.m.Y', strtotime('saturday ' . $week . ' week')))
                    ->setVisibleStart(date('d.m.Y', strtotime($eBrochure2->getStart() . ' - 1 day')))
                    ->setStoreNumber(implode(',', $extraBudgetStores))
                    ->setVariety('leaflet')
                    ->setTrackingBug($extraBudgetTrackingId . '%%CACHEBUSTER%%');

                $cBrochures->addElement($eBrochure2);
            }

            # this is only for the Saarland budget until KW 52
            $saarlandBudgetStores = [];
            foreach($aInfos['stores'] as $index => $storeNumber) {
                if(in_array($storeNumber, $saarlandBudgetStoreList) && $week <= 52) {
                    $saarlandBudgetStores[] = $storeNumber;
                    unset($aInfos['stores'][$index]);
                }
            }
            # Saarland Budget until KW52
            if(!empty($saarlandBudgetStores)) {
                $eBrochure3 = new Marktjagd_Entity_Api_Brochure();
                copy($brochurePath, preg_replace('#.pdf#', '_3.pdf', $brochurePath));
                $eBrochure3->setTitle('REWE: Wochenangebote')
                    ->setUrl(preg_replace('#.pdf#', '_3.pdf', $brochurePath))
                    ->setBrochureNumber(substr("SL_" . $brochureNumber, 0, 27) . date('W', strtotime($week . ' week')) . date('y', strtotime($week . ' week')))
                    ->setStart(date('d.m.Y', strtotime('monday ' . $week . ' week')))
                    ->setEnd(date('d.m.Y', strtotime('saturday ' . $week . ' week')))
                    ->setVisibleStart(date('d.m.Y', strtotime($eBrochure3->getStart() . ' - 1 day')))
                    ->setStoreNumber(implode(',', $saarlandBudgetStores))
                    ->setVariety('leaflet')
                    ->setTrackingBug($saarlandBudgetTrackingId . '%%CACHEBUSTER%%');

                $cBrochures->addElement($eBrochure3);
            }

            if(count($aInfos['stores'])>0) {
                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setTitle('REWE: Wochenangebote')
                    ->setUrl($brochurePath)
                    ->setBrochureNumber(substr($brochureNumber, 0, 27) . '_' . date('W', strtotime($week . ' week')) . date('y', strtotime($week . ' week')))
                    ->setStart(date('d.m.Y', strtotime('monday ' . $week . ' week')))
                    ->setEnd(date('d.m.Y', strtotime('saturday ' . $week . ' week')))
                    ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 1 day')))
                    ->setStoreNumber(implode(',', $aInfos['stores']))
                    ->setVariety('leaflet')
                    ->setTrackingBug('https://track.adform.net/adfserve/?bn=' . $trackingId .';1x1inv=1;srctype=3;gdpr=${gdpr};gdpr_consent=${gdpr_consent_50};ord=%%CACHEBUSTER%%');

                $cBrochures->addElement($eBrochure);
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function getDataFromLocalAssignmentFile(string $localAssignmentFile, Marktjagd_Service_Input_PhpSpreadsheet $sPss): array
    {
        $indexesToSearch = [3, 6];
        $aDataResult = [];

        // We search here for 'RWS Markt WAWI-Marktnummer' key headline on line 3 and 6 (which Rewe seem to change time to time)
        foreach ($indexesToSearch as $index) {
            $aData = $sPss->readFile($localAssignmentFile, true, null, $index)->getElement(0)->getData();
            foreach ($aData as $singleRow) {
                if (empty($singleRow['RWS Markt WAWI-Marktnummer'])) {
                    $this->_logger->warn("Crawler was not able to find 'RWS Markt WAWI-Marktnummer' on index: " . $index);
                    break;
                }

                $this->_logger->info("Crawler found the headline on index: " . $index);
                $aDataResult = $aData;
                break;
            }

            if(!empty($aDataResult)) {
                break;
            }
        }

        if(empty($aDataResult)) {
            throw new Exception(
                'The local Assignment file is invalid and does not contain the correct key "RWS Markt WAWI-Marktnummer".' .
                ' Check if the $localAssignmentFile is has correct healineRow when read by $sPss Excel Service'
            );
        }

        return $aDataResult;
    }
}
