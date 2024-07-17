<?php
/**
 * Brochure Crawler für Klipp WGW (ID: 73269)
 */

class Crawler_Company_KlippAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        ini_set('memory_limit', '3G');
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $sFtp->connect('templates/' . $companyId);

        $aImagesLocal = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#template\.pdf#', $singleFile)) {
                $localTemplateFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }

            if (preg_match('#\.xlsx?$#', $singleFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        foreach ($sFtp->listFiles('./images') as $singleTemplateImage) {
            if (preg_match('#\.png$#', $singleTemplateImage)) {
                $aImagesLocal[strtolower(preg_replace(['#\.png#', '#\s+#', '#é#'], ['', '-', 'e'], basename($singleTemplateImage)))] = $sFtp->downloadFtpToDir($singleTemplateImage, $localPath);
            }
        }

        foreach ($sFtp->listFiles('./picsNov') as $singleTemplateImage) {
            if (preg_match('#\.png$#', $singleTemplateImage)) {
                $aImagesLocal[strtolower(preg_replace(['#\.png#', '#\s+#', '#é#'], ['', '-', 'e'], basename($singleTemplateImage)))] = $sFtp->downloadFtpToDir($singleTemplateImage, $localPath);
            }
        }

        $sFtp->close();

        $aData = $sPhpSpreadsheet->readFile($localArticleFile, TRUE)->getElement(0)->getData();
        $aClickOutInfos = $sPdf->getAnnotationInfos($localTemplateFile);

        $amountSitesNeeded = (int)(count($aData) / 5);
        for ($i = 0; $i < $amountSitesNeeded; $i++) {
            if (copy($localTemplateFile, preg_replace('#\.pdf#', '_' . $i . '.pdf', $localTemplateFile))) {
                $aBrochureSites[$i] = preg_replace('#\.pdf#', '_' . $i . '.pdf', $localTemplateFile);
                $aReplacements = [
                    [
                        'searchPattern' => '(%%PLACEHOLDER_\d+)(%%)',
                        'replacePattern' => '$1_' . $i . '$2'
                    ]
                ];

                $fh = fopen(APPLICATION_PATH . '/../public/files/tmp/rep.json', 'w+');
                fwrite($fh, json_encode($aReplacements));
                fclose($fh);

                $aBrochureSites[$i] = $sPdf->modifyLinks($aBrochureSites[$i], APPLICATION_PATH . '/../public/files/tmp/rep.json');
            }
        }

        $templateFile = $sPdf->merge($aBrochureSites, $localPath);

        $aClickOuts = [];
        $aReplacements = [];
        $pagesSet = 0;
        foreach ($aData as $singleColumn) {
            if (!$singleColumn['Top-Artikel']) {
                continue;
            }

            $aReplacements[] = [
                'searchPattern' => '%%PLACEHOLDER_1_' . $pagesSet . '%%',
                'replacePattern' => $singleColumn['Landingpage']
            ];

            $aTitle = preg_split('#\s+#', $singleColumn['Produktbezeichnung']);
            $titleNew = [];
            $titleRow = 0;
            foreach ($aTitle as $singleWord) {
                if (strlen($titleNew[$titleRow] . ' ' . $singleWord) > 40) {
                    $titleRow++;
                }
                $titleNew[$titleRow] .= ' ' . $singleWord;
            }

            $aText = preg_split('#\s+#', $singleColumn['Beschreibung']);
            $textNew = [];
            $textRow = 0;
            foreach ($aText as $singleWord) {
                if (strlen($textNew[$textRow] . ' ' . $singleWord) > 60) {
                    $textRow++;
                }
                $textNew[$textRow] .= ' ' . $singleWord;
            }

            $aClickOuts[] =
                [
                    'page' => $pagesSet,
                    'startX' => 45.0,
                    'startY' => 750.0,
                    'type' => 'text',
                    'contents' => 'Aktuelle KLIPP Aktionen',
                    'font' => ['fontType' => 'Trade_Bold', 'fontSize' => 45, 'fontColor' => '0|0|0']
                ];


            $aClickOuts[] =
                [
                    'page' => $pagesSet,
                    'startX' => $aClickOutInfos[0]->rectangle->startX,
                    'startY' => $aClickOutInfos[0]->rectangle->startY + 10.0,
                    'endX' => $aClickOutInfos[0]->rectangle->endX,
                    'endY' => $aClickOutInfos[0]->rectangle->endY - 10.0,
                    'type' => 'image',
                    'path' => $aImagesLocal[$singleColumn['Foto-Nr.']],
                    'scaling' => TRUE
                ];
            $aClickOuts[] =
                [
                    'page' => $pagesSet,
                    'startX' => $aClickOutInfos[0]->width - 100.0,
                    'startY' => $aClickOutInfos[0]->height - 150.0,
                    'endX' => $aClickOutInfos[0]->width,
                    'endY' => $aClickOutInfos[0]->height,
                    'type' => 'image',
                    'path' => $aImagesLocal['image_' . ($pagesSet + 1)],
                    'scaling' => FALSE
                ];

            $aClickOuts[] =
                [
                    'page' => $pagesSet,
                    'startX' => 250.0,
                    'startY' => 605.0,
                    'endX' => 330.0,
                    'endY' => 685.0,
                    'type' => 'image',
                    'path' => $aImagesLocal['price_big'],
                    'scaling' => TRUE
                ];

            $movementX = 0.0;
            if (strlen((string)(int)$singleColumn['Aktionspreis']) >= 1) {
                $movementX = 4.0;
            }

            if (is_float($singleColumn['Stattpreis'])) {
                $movement = 0.0;
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => 320.0 - (strlen((string)number_format($singleColumn['Stattpreis'], 2)) * 10.0) - $movementX,
                        'startY' => 655.0,
                        'type' => 'text',
                        'contents' => number_format($singleColumn['Stattpreis'], 2),
                        'font' => ['fontType' => 'Trade', 'fontSize' => 15, 'fontColor' => '255|255|255']
                    ];

                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => 320.0 - (strlen((string)number_format($singleColumn['Stattpreis'], 2)) * 10.0) - $movementX,
                        'startY' => 653.0,
                        'endX' => 315.0,
                        'endY' => 668.0,
                        'type' => 'line',
                        'line' => ['lineWidth' => 1, 'lineColor' => '255|255|255']
                    ];
            } else {
                $movement = 5.0;
            }

            if (is_null($singleColumn['Aktionspreis']) || !strlen($singleColumn['Aktionspreis'])) {
                $priceStamp = 'GRATIS';
                if (!is_null($singleColumn['Stattpreis']) && is_float($singleColumn['Stattpreis'])) {
                    $priceStamp = number_format((float)$singleColumn['Stattpreis'], 2);
                }
                if (!is_null($singleColumn['Stattpreis']) && !is_float($singleColumn['Stattpreis'])) {
                    $priceStamp = $singleColumn['Stattpreis'];
                }
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => 290.0 - (strlen($priceStamp) * 5.0),
                        'startY' => 635.0 + $movement,
                        'type' => 'text',
                        'contents' => $priceStamp,
                        'font' => ['fontType' => 'Trade_Bold', 'fontSize' => 20, 'fontColor' => '255|255|255']
                    ];
            } else {
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => 315.0 - (strlen((string)number_format($singleColumn['Aktionspreis'], 2)) * 10.0) - $movementX,
                        'startY' => 635.0 + $movement,
                        'type' => 'text',
                        'contents' => number_format($singleColumn['Aktionspreis'], 2),
                        'font' => ['fontType' => 'Trade_Bold', 'fontSize' => 20, 'fontColor' => '255|255|255']
                    ];
            }

            $row = 0;
            foreach ($titleNew as $singleRow) {
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aClickOutInfos[0]->rectangle->endX + 25.0,
                        'startY' => $aClickOutInfos[0]->rectangle->startY + (($aClickOutInfos[0]->rectangle->endY - $aClickOutInfos[0]->rectangle->startY) / 2) - $row++ * 15.0,
                        'type' => 'text',
                        'contents' => trim($singleRow),
                        'font' => ['fontType' => 'Trade_Bold', 'fontSize' => 15, 'fontColor' => '0|0|0']
                    ];
            }
            foreach ($textNew as $singleRow) {
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aClickOutInfos[0]->rectangle->endX + 25.0,
                        'startY' => $aClickOutInfos[0]->rectangle->startY + (($aClickOutInfos[0]->rectangle->endY - $aClickOutInfos[0]->rectangle->startY) / 2) - 10.0 - $row++ * 12.0,
                        'type' => 'text',
                        'contents' => trim($singleRow),
                        'font' => ['fontType' => 'Trade', 'fontSize' => 10, 'fontColor' => '0|0|0']
                    ];
            }
            $pagesSet++;
        }

        $pagesSet = 0;
        $count = 1;
        foreach ($aData as $singleColumn) {
            if ($singleColumn['Top-Artikel']) {
                continue;
            }

            $aReplacements[] = [
                'searchPattern' => '%%PLACEHOLDER_' . ($count + 1) . '_' . $pagesSet . '%%',
                'replacePattern' => $singleColumn['Landingpage']
            ];

            $aTitle = preg_split('#\s+#', $singleColumn['Produktbezeichnung']);
            $titleNew = [];
            $titleRow = 0;
            foreach ($aTitle as $singleWord) {
                if (strlen($titleNew[$titleRow] . ' ' . $singleWord) > 20) {
                    $titleRow++;
                }
                $titleNew[$titleRow] .= ' ' . $singleWord;
            }

            $aText = preg_split('#\s+#', $singleColumn['Beschreibung']);
            $textNew = [];
            $textRow = 0;
            foreach ($aText as $singleWord) {
                if (strlen($textNew[$textRow] . ' ' . $singleWord) > 34) {
                    $textRow++;
                }
                $textNew[$textRow] .= ' ' . $singleWord;
            }

            $aClickOuts[] =
                [
                    'page' => $pagesSet,
                    'startX' => $aClickOutInfos[$count]->rectangle->startX,
                    'startY' => $aClickOutInfos[$count]->rectangle->startY + 5.0,
                    'endX' => $aClickOutInfos[$count]->rectangle->endX,
                    'endY' => $aClickOutInfos[$count]->rectangle->endY - 5.0,
                    'type' => 'image',
                    'path' => $aImagesLocal[$singleColumn['Foto-Nr.']],
                    'scaling' => TRUE
                ];

            $aClickOuts[] =
                [
                    'page' => $pagesSet,
                    'startX' => $aClickOutInfos[$count]->rectangle->endX,
                    'startY' => $aClickOutInfos[$count]->rectangle->endY - 60.0,
                    'endX' => $aClickOutInfos[$count]->rectangle->endX + 50.0,
                    'endY' => $aClickOutInfos[$count]->rectangle->endY - 10.0,
                    'type' => 'image',
                    'path' => $aImagesLocal['price_big'],
                    'scaling' => TRUE
                ];

            $movementX = 0.0;
            if (strlen((string)(int)$singleColumn['Aktionspreis']) == 1) {
                $movementX = 4.0;
            }

            if (is_float($singleColumn['Stattpreis'])) {
                $movement = 0.0;
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aClickOutInfos[$count]->rectangle->endX + 48.0 - (strlen((string)number_format($singleColumn['Stattpreis'], 2)) * 6.0) - $movementX,
                        'startY' => $aClickOutInfos[$count]->rectangle->endY - 33.0,
                        'type' => 'text',
                        'contents' => number_format((float)$singleColumn['Stattpreis'], 2),
                        'font' => ['fontType' => 'Trade', 'fontSize' => 8, 'fontColor' => '255|255|255']
                    ];

                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aClickOutInfos[$count]->rectangle->endX + 45.0 - (strlen((string)number_format($singleColumn['Stattpreis'], 2)) * 6.0) - $movementX,
                        'startY' => $aClickOutInfos[$count]->rectangle->endY - 35.0,
                        'endX' => $aClickOutInfos[$count]->rectangle->endX + 40.0,
                        'endY' => $aClickOutInfos[$count]->rectangle->endY - 25.0,
                        'type' => 'line',
                        'line' => ['lineWidth' => 0.5, 'lineColor' => '255|255|255']
                    ];
            } else {
                $movement = 5.0;
            }
            if (is_null($singleColumn['Aktionspreis'])) {
                $priceStamp = 'GRATIS';
                if (!is_null($singleColumn['Stattpreis']) && is_float($singleColumn['Stattpreis'])) {
                    $priceStamp = number_format((float)$singleColumn['Stattpreis'], 2);
                }
                if (!is_null($singleColumn['Stattpreis']) && !is_float($singleColumn['Stattpreis'])) {
                    $priceStamp = $singleColumn['Stattpreis'];
                }
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aClickOutInfos[$count]->rectangle->endX + 30.0 - (strlen($priceStamp) * 4.0),
                        'startY' => $aClickOutInfos[$count]->rectangle->endY - 45.0 + $movement,
                        'type' => 'text',
                        'contents' => $priceStamp,
                        'font' => ['fontType' => 'Trade_Bold', 'fontSize' => 12, 'fontColor' => '255|255|255']
                    ];
            } else {
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aClickOutInfos[$count]->rectangle->endX + 50.0 - (strlen((string)number_format($singleColumn['Aktionspreis'], 2)) * 8.0) - $movementX,
                        'startY' => $aClickOutInfos[$count]->rectangle->endY - 45.0 + $movement,
                        'type' => 'text',
                        'contents' => number_format((float)$singleColumn['Aktionspreis'], 2),
                        'font' => ['fontType' => 'Trade_Bold', 'fontSize' => 12, 'fontColor' => '255|255|255']
                    ];
            }


            $row = 0;
            foreach ($titleNew as $singleRow) {
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aClickOutInfos[$count]->rectangle->endX + 5.0,
                        'startY' => $aClickOutInfos[$count]->rectangle->startY + (($aClickOutInfos[0]->rectangle->endY - $aClickOutInfos[0]->rectangle->startY) / 2) - $row++ * 12.0,
                        'type' => 'text',
                        'contents' => trim($singleRow),
                        'font' => ['fontType' => 'Trade_Bold', 'fontSize' => 10, 'fontColor' => '0|0|0']
                    ];
            }
            foreach ($textNew as $singleRow) {
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aClickOutInfos[$count]->rectangle->endX + 5.0,
                        'startY' => $aClickOutInfos[$count]->rectangle->startY + (($aClickOutInfos[0]->rectangle->endY - $aClickOutInfos[0]->rectangle->startY) / 2) - 5.0 - $row++ * 10.0,
                        'type' => 'text',
                        'contents' => trim($singleRow),
                        'font' => ['fontType' => 'Trade', 'fontSize' => 7, 'fontColor' => '0|0|0']
                    ];
            }
            if ($count++ == 4) {
                $pagesSet++;
                $count = 1;
            }
        }

        $jsonFilePath = APPLICATION_PATH . '/../public/files/tmp/test.json';

        $fh = fopen($jsonFilePath, 'w+');
        fwrite($fh, json_encode($aClickOuts));
        fclose($fh);
        $fileNameInserted = $sPdf->addElements(
            $templateFile,
            $jsonFilePath);

        $fh = fopen(APPLICATION_PATH . '/../public/files/tmp/replaceFinal.json', 'w+');
        fwrite($fh, json_encode($aReplacements));
        fclose($fh);

        $fileNameInserted = $sPdf->modifyLinks(
            $fileNameInserted,
            APPLICATION_PATH . '/../public/files/tmp/replaceFinal.json');

        if (rename($fileNameInserted, APPLICATION_PATH . '/../public/files/tmp/new.pdf')) {
            Zend_Debug::dump('done!');
            die;
        }
    }
}
