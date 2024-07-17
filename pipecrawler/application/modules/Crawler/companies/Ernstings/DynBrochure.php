<?php


class Crawler_Company_Ernstings_DynBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        $localPath = APPLICATION_PATH . '/../public/files/ftp/22133/2020-08-13-11-39-58/';
        $aFilesToUse = [];
        foreach ($sFtp->listFiles('./dyn') as $singleFile) {
            if (preg_match('#template_clickouts\.pdf#', $singleFile)) {
                $localClickoutFilePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }
            if (preg_match('#articles_kids\.xls#', $singleFile)) {
                $localArticleFilePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }
            if (preg_match('#_([^\._]+?)\.pdf#', $singleFile, $siteMatch)) {
                $aFilesToUse[$siteMatch[1]] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();

//        $aFilesToMerge[0] = $this->_createTitle($aFilesToUse['title']);
        $aClickoutInfos = $sPdf->getAnnotationInfos($localClickoutFilePath);

        $aData = $sPss->readFile($localArticleFilePath, TRUE)->getElement(0)->getData();

        $aArticleData = [];
        foreach ($aData as $singleRow) {
            if (preg_match('#Titel#', $singleRow['Seiten-Typ'])) {
                continue;
            }
            if (!strlen($aArticleData[$singleRow['Seiten-Nr.']]['type'])) {
                $aArticleData[$singleRow['Seiten-Nr.']]['type'] = $singleRow['Seiten-Typ'];
            }
            if (!strlen($aArticleData[$singleRow['Seiten-Nr.']]['category'])) {
                $aArticleData[$singleRow['Seiten-Nr.']]['category'] = $singleRow['Prospekt-Themenwelt (PIM)'];
            }
            $aArticleData[$singleRow['Seiten-Nr.']]['articles'][] = [
                'title' => $singleRow['Artikel-Name (PIM)'],
                'price' => $singleRow['OVK (P&A)'],
                'image' =>
//                    $sHttp->getRemoteFile(
                    $localPath . basename(
                        trim($singleRow['Image-Link (IT)'])
//                      , $localPath
                    )
                ,
                'url' => $singleRow['Tracking-Link Marktjagd']
            ];
        }

        foreach ($aArticleData as $siteNo => $aInfos) {
            $aInfosToAdd = [];
            $aClickouts = [];
            $k = 0;
            if (preg_match('#^II$#', $aInfos['type'])) {
                if (count($aInfos['articles']) == 7) {
                    $aInfosToAdd[] =
                        [
                            'page' => 0,
                            'startX' => $aClickoutInfos[0]->rectangle->startX + 50.0,
                            'startY' => $aClickoutInfos[0]->rectangle->startY + 25.0,
                            'type' => 'text',
                            'contents' => 'MOOD',
                            'font' => ['fontType' => 'Helvetica_Bold', 'fontSize' => 8, 'fontColor' => '0|0|0']
                        ];
                    $aInfosToAdd[] =
                        [
                            'page' => 0,
                            'startX' => $aClickoutInfos[7]->rectangle->startX + 50.0,
                            'startY' => $aClickoutInfos[7]->rectangle->startY + 25.0,
                            'type' => 'text',
                            'contents' => 'MOOD',
                            'font' => ['fontType' => 'Helvetica_Bold', 'fontSize' => 8, 'fontColor' => '0|0|0']
                        ];
                } elseif (count($aInfos['articles']) == 8) {
                    $aInfosToAdd[] =
                        [
                            'page' => 0,
                            'startX' => $aClickoutInfos[4]->rectangle->startX + 50.0,
                            'startY' => $aClickoutInfos[4]->rectangle->startY + 25.0,
                            'type' => 'text',
                            'contents' => 'MOOD',
                            'font' => ['fontType' => 'Helvetica_Bold', 'fontSize' => 8, 'fontColor' => '0|0|0']
                        ];
                }
            }
            if (preg_match('#^I$#', $aInfos['type'])) {
                $k = 3;
                if (!copy($aFilesToUse[$aInfos['category']], preg_replace('#\.pdf#', '_' . $siteNo . '.pdf', $aFilesToUse[$aInfos['category']]))) {
                    throw new Exception($companyId . ': unable to copy page: ' . $aFilesToUse[$aInfos['type']]);
                }
                $pageName = preg_replace('#\.pdf#', '_' . $siteNo . '.pdf', $aFilesToUse[$aInfos['category']]);
            } else {
                if (!copy($aFilesToUse['III'], preg_replace('#\.pdf#', '_' . $siteNo . '.pdf', $aFilesToUse['III']))) {
                    throw new Exception($companyId . ': unable to copy page: ' . $aFilesToUse[$aInfos['type']]);
                }
                $pageName = preg_replace('#\.pdf#', '_' . $siteNo . '.pdf', $aFilesToUse['III']);
            }
            for ($i = 0; $i < count($aInfos['articles']); $i++) {
                $set = $i + $k;
                if (preg_match('#^II$#', $aInfos['type'])) {
                    if ($set == 0 && count($aInfos['articles']) == 7) {
                        $set = 8;
                    }
                    if ($set == 4 && count($aInfos['articles']) == 8) {
                        $set = 8;
                    }
                }
                $aInfosToAdd[] =
                    [
                        'page' => 0,
                        'startX' => $aClickoutInfos[$set]->rectangle->startX + 5.0,
                        'startY' => $aClickoutInfos[$set]->rectangle->startY + 50.0,
                        'endX' => $aClickoutInfos[$set]->rectangle->endX - 5.0,
                        'endY' => $aClickoutInfos[$set]->rectangle->endY - 5.0,
                        'type' => 'image',
                        'path' => $aInfos['articles'][$i]['image'],
                        'scaling' => TRUE
                    ];
                $aInfosToAdd[] = [
                    'page' => 0,
                    'startX' => $aClickoutInfos[$set]->rectangle->startX + ($aClickoutInfos[$i]->rectangle->endX - $aClickoutInfos[$i]->rectangle->startX - 3 * strlen($aInfos['articles'][$i]['title'])) / 2,
                    'startY' => $aClickoutInfos[$set]->rectangle->startY + 25.0,
                    'type' => 'text',
                    'contents' => $aInfos['articles'][$i]['title'],
                    'font' => ['fontType' => 'Arimo', 'fontSize' => 6, 'fontColor' => '0|0|0']
                ];

                $aInfosToAdd[] = [
                    'page' => 0,
                    'startX' => $aClickoutInfos[$set]->rectangle->startX + ($aClickoutInfos[$i]->rectangle->endX - $aClickoutInfos[$i]->rectangle->startX - 4 * strlen($aInfos['articles'][$i]['price'] . ' €')) / 2,
                    'startY' => $aClickoutInfos[$set]->rectangle->startY + 5.0,
                    'type' => 'text',
                    'contents' => $aInfos['articles'][$i]['price'] . ' €',
                    'font' => ['fontType' => 'Arimo', 'fontSize' => 12, 'fontColor' => '0|0|0']
                ];

                $aClickouts[] = [
                    'page' => 0,
                    'startX' => $aClickoutInfos[$set]->rectangle->startX,
                    'startY' => $aClickoutInfos[$set]->rectangle->startY,
                    'endX' => $aClickoutInfos[$set]->rectangle->endX,
                    'endY' => $aClickoutInfos[$set]->rectangle->endY,
                    'link' => $aInfos['articles'][$i]['url']
                ];

            }


            if (preg_match('#^I$#', $aInfos['type'])) {
                if (preg_match('#CT#', $aInfos['category'])) {
                    $textA = 'Citytrip -';
                    $textB = 'Neue Looks für den Spätsommer';
                }

                if (preg_match('#UM#', $aInfos['category'])) {
                    $textA = 'Übergangs-Mode -';
                    $textB = 'Neue Farben und Prints für junge Trendsetter';
                }

                $aInfosToAdd[] = [
                    'page' => 0,
                    'startX' => 5.0,
                    'startY' => 315.0,
                    'type' => 'text',
                    'contents' => $textA,
                    'font' => ['fontType' => 'Interstate_Condensed', 'fontSize' => 10, 'fontColor' => '255|255|255']
                ];

                $aInfosToAdd[] = [
                    'page' => 0,
                    'startX' => 5.0,
                    'startY' => 305.0,
                    'type' => 'text',
                    'contents' => $textB,
                    'font' => ['fontType' => 'Interstate_Condensed', 'fontSize' => 10, 'fontColor' => '255|255|255']
                ];
            }

            $jsonFilePath = APPLICATION_PATH . '/../public/files/tmp/elements.json';

            $fh = fopen($jsonFilePath, 'w+');
            fwrite($fh, json_encode($aInfosToAdd));
            fclose($fh);

            $sPdf->cleanAnnotations($pageName);
            $fileNameInserted = $sPdf->addElements(
                $pageName,
                $jsonFilePath);

            $jsonFilePath = APPLICATION_PATH . '/../public/files/tmp/cos.json';

            $fh = fopen($jsonFilePath, 'w+');
            fwrite($fh, json_encode($aClickouts));
            fclose($fh);

            $fileNameLinked = $sPdf->setAnnotations($fileNameInserted, $jsonFilePath);

            $aFilesToMerge[$siteNo] = $fileNameLinked;
        }
        $aFilesToMerge[] = $aFilesToUse['end'];
        Zend_Debug::dump($sPdf->merge($aFilesToMerge, $localPath));
        die;
    }

    protected
    function _createTitle($titleFile)
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $aTitleInfos = $sPdf->getAnnotationInfos($titleFile);
        $aTitleTexts = [];
        foreach ($aTitleInfos as $titleInfoToUse) {
            if (preg_match('#validity#', $titleInfoToUse->url)) {
                continue;
            } elseif (preg_match('#line_(\d)#', $titleInfoToUse->url, $lineMatch)) {
                {
                    switch ($lineMatch[1]) {
                        case '1':
                        {
                            $text = 'SPÄTSOMMER';
                            $size = 25;
                            break;
                        }
                        case '2':
                        {
                            $text = 'TRENDS';
                            $size = 45;
                        }
                    }
                }

                $aTitleTexts[] = [
                    'page' => $titleInfoToUse->page,
                    'startX' => $titleInfoToUse->rectangle->startX,
                    'startY' => $titleInfoToUse->rectangle->startY,
                    'type' => 'text',
                    'contents' => $text,
                    'font' => ['fontType' => 'Interstate_Condensed', 'fontSize' => $size, 'fontColor' => '255|255|255']
                ];
                continue;
            }
        }

        $jsonFilePath = APPLICATION_PATH . '/../public/files/tmp/test.json';

        $fh = fopen($jsonFilePath, 'w+');
        fwrite($fh, json_encode($aTitleTexts));
        fclose($fh);

        $fileNameInserted = $sPdf->addElements(
            $titleFile,
            $jsonFilePath);

        $sPdf->cleanAnnotations($fileNameInserted);

        return $fileNameInserted;
    }
}