<?php

/*
 * Prospekt Crawler für ROFU (ID: 28773)
 */

class Crawler_Company_Rofu_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        mkdir($localPath . 'Wechselseiten/', 0775, true);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.pdf$#', $singleFile)) {
                $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Wechselseiten#', $singleFile)) {
                $sFtp->changedir($singleFile);
                foreach ($sFtp->listFiles() as $singleFile) {
                    if (!preg_match('#^\.#', $singleFile)) {
                        mkdir($localPath . 'Wechselseiten/' . $singleFile, 0775, true);
                        foreach ($sFtp->listFiles($singleFile) as $singlePdf) {
                            if (preg_match('#\.pdf$#', $singlePdf)) {
                                $sFtp->downloadFtpToDir($singlePdf, $localPath . 'Wechselseiten/' . $singleFile . '/');
                                continue;
                            }
                        }
                    }
                }
            }
        }

        $aNeutralFiles = array();
        $aFilesToExchange = array();
        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#\.pdf$#', $singleFile) && !preg_match('#[A-ZÄÖÜ]\.pdf$#', $singleFile) && !preg_match('#separated_(\d+)\.pdf$#', $singleFile)) {
                $sPdf->splitPdf($localPath . $singleFile);
                foreach (scandir($localPath) as $singleSeparatedFile) {
                    if (preg_match('#separated_(\d+)\.pdf$#', $singleSeparatedFile, $siteMatch)) {
                        $aNeutralFiles[$siteMatch[1]] = $localPath . $singleSeparatedFile;
                    }
                }
                ksort($aNeutralFiles);
                break;
            }
        }
        foreach (scandir($localPath . 'Wechselseiten/') as $singleFile) {
            if (!preg_match('#^\.#', $singleFile)) {
                foreach (scandir($localPath . 'Wechselseiten/' . $singleFile) as $singlePdf) {
                    if (preg_match('#_S\.?(\d+)_[A-ZÄÖÜ]{2,3}(\d+)[^\._]*\.pdf$#', $singlePdf, $infoMatch)) {
                        $aFilesToExchange[$infoMatch[2]][(int) $infoMatch[1]] = $localPath . 'Wechselseiten/' . $singleFile . '/' . $singlePdf;
                    } elseif (preg_match('#_S\.?(\d{2})_(VKOSO\d{4})\.pdf$#', $singlePdf, $infoMatch)) {
                        $aFilesToExtra[$infoMatch[2]][(int) $infoMatch[1]] = $localPath . 'Wechselseiten/' . $singleFile . '/' . $singlePdf;
                    }
                }
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $aUsedStoreNumbers = array();

        foreach ($aFilesToExchange as $storeNumber => $aFilesToChangePerStore) {
            $weekend = FALSE;
            $aFilesPerStore = $aNeutralFiles;
            if (!preg_match('#KW(\d{1,2})#', $aFilesPerStore[1], $weekMatch)) {
                $this->_logger->err($companyId . ': unable to get week.');
            }
            foreach ($aFilesToChangePerStore as $site => $singleFileToChange) {
                if (preg_match('#(VKOSO_(\d{2})_(\d{2}))#', $singleFileToChange, $weekendMatch)) {
                    $date = strtotime($weekendMatch[2] . '.' . $weekendMatch[3] . '.' . $sTimes->getWeeksYear());
                    $weekend = TRUE;
                }
                $aFilesPerStore[$site] = $singleFileToChange;
            }
            if ($weekend && array_key_exists(preg_replace('#_#', '', $weekendMatch[1]), $aFilesToExtra)) {
                foreach ($aFilesToExtra[preg_replace('#_#', '', $weekendMatch[1])] as $site => $filePath) {
                    $aFilesPerStore[(int) $site] = $filePath;
                }
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $pdfPath = $sPdf->trim($sPdf->merge($aFilesPerStore, $localPath));

            $jsonFilePath = $localPath . 'linkData.json';
            $fh = fopen($jsonFilePath, 'w+');
            fwrite($fh, '[{"page":' . (count($aFilesPerStore) - 1) . ',"link":"http://www.rofu.de/","startX":441.687,"startY":122.98,"width":50,"height":50}]');
            fclose($fh);

            $eBrochure->setUrl($sFtp->generatePublicFtpUrl($sPdf->setAnnotations($pdfPath, $jsonFilePath)))
                    ->setTitle('Wochen Angebote')
                    ->setVariety('leaflet')
                    ->setStoreNumber((int) $storeNumber)
                    ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear(), $weekMatch[1], 'Mo'))
                    ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear(), $weekMatch[1], 'Sa'))
                    ->setVisibleStart($eBrochure->getStart());

            if (strlen($date)) {
                if ($date > strtotime($eBrochure->getStart())) {
                    $eBrochure->setEnd(date('d.m.Y', $date));
                } elseif ($date < strtotime($eBrochure->getStart())) {
                    $eBrochure->setStart(date('d.m.Y', $date))
                            ->setVisibleStart($eBrochure->getStart());
                }
            }

            $cBrochures->addElement($eBrochure);
            $aUsedStoreNumbers[] = $storeNumber;
        }

        $cStores = $sApi->findStoresByCompany($companyId);
        $aNeutralStores = array();
        foreach ($cStores->getElements() as $eStore) {
            if (!in_array($eStore->getStoreNumber(), $aUsedStoreNumbers)) {
                $aNeutralStores[] = $eStore->getStoreNumber();
            }
        }

        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $pdfPath = $sPdf->trim($sPdf->merge($aNeutralFiles, $localPath));
        $jsonFilePath = $localPath . 'linkData.json';
        $fh = fopen($jsonFilePath, 'w+');
        fwrite($fh, '[{"page":' . (count($aNeutralFiles) - 1) . ',"link":"http://www.rofu.de/","startX":441.687,"startY":122.98,"width":50,"height":50}]');
        fclose($fh);

        $eBrochure->setUrl($sFtp->generatePublicFtpUrl($sPdf->setAnnotations($pdfPath, $jsonFilePath)))
                ->setTitle('Wochen Angebote')
                ->setVariety('leaflet')
                ->setStoreNumber(implode(',', $aNeutralStores))
                ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear(), $weekMatch[1], 'Mo'))
                ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear(), $weekMatch[1], 'Sa'))
                ->setVisibleStart($eBrochure->getStart());

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
