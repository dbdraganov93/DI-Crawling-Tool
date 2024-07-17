<?php
/**
 * Brochure Crawler für Alnatura (ID: 22232)
 */

class Crawler_Company_Alnatura_BrochureNew extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sArticleCrawler = new Crawler_Company_Alnatura_Article();

        $localPath = APPLICATION_PATH . '/../public/files/tmp/';
        $fileName = $sArticleCrawler->crawl($companyId)->getFileName();

        $sS3File = new Marktjagd_Service_Output_S3File('mjcsv', $fileName);
        $file = $sS3File->getFileFromBucket($fileName, $localPath);

        $sPhpSs = new Marktjagd_Service_Input_PhpSpreadsheet();

        $aData = $sPhpSs->readFile($file, TRUE, ';')->getElement(0)->getData();

        $aArticles = [];
        $strStart = '';
        $strEnd = '';
        foreach ($aData as $singleColumn) {
            if (!strlen($strStart)) {
                $strStart = $singleColumn['start'];
            }
            if (!strlen($strEnd)) {
                $strEnd = $singleColumn['end'];
            }
            $aArticles[] = [
                'title' => $singleColumn['title'],
                'price' => $singleColumn['price'],
                'manufacturer' => $singleColumn['manufacturer'],
                'suggested_retail_price' => $singleColumn['suggested_retail_price'],
                'amount' => $singleColumn['amount'],
                'image' => preg_replace('#([^\?]+?)\?.+#', '$1', $singleColumn['image']),
                'url' => $singleColumn['url'],
                'text' => $singleColumn['text']
            ];
        }

        $sFtp->connect($companyId);
        $aLocalTemplateFiles = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#template[^\.]*teil(\d+)[^\.]*\.pdf$#i', $singleFile, $partMatch)) {
                $aLocalTemplateFiles[$partMatch[1]] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();

        $sPdf = new Marktjagd_Service_Output_Pdf();

        $aCoordsClickouts = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => [],
            6 => []
        ];

        foreach ($sPdf->getAnnotationInfos($aLocalTemplateFiles[1]) as $singleClickout) {
            $aCoordsClickouts[preg_replace('#[^\d]#', '', $singleClickout->url)] = [
                'startX' => $singleClickout->rectangle->startX,
                'startY' => $singleClickout->rectangle->startY,
                'endX' => $singleClickout->rectangle->endX,
                'endY' => $singleClickout->rectangle->endY,
                'width' => $singleClickout->width,
                'height' => $singleClickout->height,
            ];
        }

        $amountSitesNeeded = (int)(count($aArticles) / 6);
        if ($amountSitesNeeded > 11) {
            $amountSitesNeeded = 11;
        }
        for ($i = 0; $i < $amountSitesNeeded; $i++) {
            if (copy($aLocalTemplateFiles[($i % 2) + 1], preg_replace('#\.pdf#', '_' . $i . '.pdf', $aLocalTemplateFiles[($i % 2) + 1]))) {
                $aBrochureSites[$i] = preg_replace('#\.pdf#', '_' . $i . '.pdf', $aLocalTemplateFiles[($i % 2) + 1]);
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

        for ($i = 0; $i < count($aBrochureSites); $i++) {
            $validStartX = 120.0;
//            if ($i % 2 != 0) {
//                $validStartX = 395.0;
//            }

            $aClickOuts[] =
                [
                    'page' => $i,
                    'startX' => $validStartX,
                    'startY' => 657.5,
                    'type' => 'text',
                    'contents' => 'gültig vom ' . $strStart . ' bis ' . $strEnd,
                    'font' => ['fontType' => 'Frutiger_Bold', 'fontSize' => 10, 'fontColor' => '255|255|255']
                ];
        }

        $pagesSet = 0;
        $count = 1;
        $localImagesAmount = 0;

        foreach ($aArticles as $singleColumn) {
            $localImagePath = $sHttp->getRemoteFile($singleColumn['image'], $localPath);
            if (!$localImagePath) {
                $this->_logger->warn('Couldn\'t download image: ' . $singleColumn['image']);
                continue;
            }
            $im = new imagick($localImagePath);
            $im->setImageFormat('png');
            $im->writeImage(preg_replace('#\.ashx#', '.png', $localImagePath));
            $im->clear();
            $im->destroy();

            $localImagePath = preg_replace('#\.ashx#', '.png', $localImagePath);
            $localImagesAmount++;

            $aClickOuts[] =
                [
                    'page' => $pagesSet,
                    'startX' => $aCoordsClickouts[$count]['startX'] + 5.0,
                    'startY' => $aCoordsClickouts[$count]['startY'] + 30.0,
                    'endX' => $aCoordsClickouts[$count]['endX'] - 5.0,
                    'endY' => $aCoordsClickouts[$count]['endY'] - 5.0,
                    'type' => 'image',
                    'path' => $localImagePath,
                    'scaling' => TRUE
                ];

            $aClickOuts[] =
                [
                    'page' => $pagesSet,
                    'startX' => $aCoordsClickouts[$count]['endX'] + 8.0,
                    'startY' => $aCoordsClickouts[$count]['startY'] + 150.0,
                    'type' => 'text',
                    'contents' => $singleColumn['manufacturer'],
                    'font' => ['fontType' => 'Frutiger_Bold', 'fontSize' => 12, 'fontColor' => '139|37|76']
                ];

            $aTitle = [];
            $aTitleOrig = preg_split('#\s+#', $singleColumn['title']);
            $line = 0;
            foreach ($aTitleOrig as $singleWord) {
                if (strlen($aTitle[$line] . ' ' . $singleWord) > 25) {
                    $line++;
                }
                $aTitle[$line] .= ' ' . $singleWord;
            }
            foreach ($aTitle as $singleLine => $content) {
                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aCoordsClickouts[$count]['endX'] + 5.0,
                        'startY' => $aCoordsClickouts[$count]['startY'] + 130.0 - (12 * $singleLine),
                        'type' => 'text',
                        'contents' => $content,
                        'font' => ['fontType' => 'Frutiger_Bold', 'fontSize' => 10, 'fontColor' => '94|94|94']
                    ];
            }
            $aClickOuts[] =
                [
                    'page' => $pagesSet,
                    'startX' => $aCoordsClickouts[$count]['endX'] + 8.0,
                    'startY' => $aCoordsClickouts[$count]['startY'] + 95.0,
                    'type' => 'text',
                    'contents' => $singleColumn['amount'],
                    'font' => ['fontType' => 'Frutiger_Bold', 'fontSize' => 9, 'fontColor' => '145|144|141']
                ];

            

            if (isset($singleColumn['text'])) {
                if ($singleColumn['text'] != "Abtropfgewicht 0 kg") {
                    $aClickOuts[] =
                        [
                            'page' => $pagesSet,
                            'startX' => $aCoordsClickouts[$count]['endX'] + 8.0,
                            'startY' => $aCoordsClickouts[$count]['startY'] + 75.0,
                            'type' => 'text',
                            'contents' => $singleColumn['text'],
                            'font' => ['fontType' => 'Frutiger_Bold', 'fontSize' => 9, 'fontColor' => '145|144|141']
                        ];
                    }
            }

            $fontColorStandard = '139|37|76';

            if (strlen($singleColumn['suggested_retail_price'])) {
                $fontColorStandard = '205|23|25';

                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aCoordsClickouts[$count]['endX'] + 15.0,
                        'startY' => $aCoordsClickouts[$count]['startY'] + 37.0,
                        'type' => 'text',
                        'contents' => $singleColumn['suggested_retail_price'],
                        'font' => ['fontType' => 'Frutiger_Bold', 'fontSize' => 10, 'fontColor' => '94|94|94']
                    ];

                $aClickOuts[] =
                    [
                        'page' => $pagesSet,
                        'startX' => $aCoordsClickouts[$count]['endX'] + 12.0,
                        'startY' => $aCoordsClickouts[$count]['startY'] + 40.0,
                        'endX' => $aCoordsClickouts[$count]['endX'] + 39.0,
                        'endY' => $aCoordsClickouts[$count]['startY'] + 40.0,
                        'type' => 'line',
                        'line' => ['lineWidth' => 1, 'lineColor' => '94|94|94']
                    ];

            }

            $aClickOuts[] =
                [
                    'page' => $pagesSet,
                    'startX' => $aCoordsClickouts[$count]['endX'] + 45.0,
                    'startY' => $aCoordsClickouts[$count]['startY'] + 35.0,
                    'type' => 'text',
                    'contents' => $singleColumn['price'] . ' €',
                    'font' => ['fontType' => 'Frutiger_Bold', 'fontSize' => 21, 'fontColor' => $fontColorStandard]
                ];


            $count++;
            if (($localImagesAmount % 6) == 0) {
                $count = 1;
                $pagesSet++;
            }
            if ($pagesSet == $amountSitesNeeded) {
                break;
            }
        }

        $jsonFilePath = APPLICATION_PATH . '/../public/files/tmp/test.json';

        $fh = fopen($jsonFilePath, 'w+');
        fwrite($fh, json_encode($aClickOuts));
        fclose($fh);

        $fileNameInserted = $sPdf->addElements(
            $templateFile,
            $jsonFilePath);

        $sPdf->cleanAnnotations($fileNameInserted);

        $aAnnot = [
            [
                "page" => 0,
                "height" => 840,
                "width" => 595,
                "startX" => 0,
                "startY" => 650,
                "endX" => 595,
                "endY" => 840,
                "link" => "https://angebote.alnatura.de/de-de/"
            ]
        ];

        $fh = fopen(APPLICATION_PATH . '/../public/files/tmp/header.json', 'w+');
        fwrite($fh, json_encode($aAnnot));
        fclose($fh);

        $fileNameInserted = $sPdf->setAnnotations(
            $fileNameInserted,
            APPLICATION_PATH . '/../public/files/tmp/header.json');

        $fileNameInserted = $sPdf->merge([$fileNameInserted, $aLocalTemplateFiles[3]], $localPath);

        $aCampaignStores = [
            'id:209660',
            'id:209667',
            'id:209668',
            'id:209680',
            'id:209687',
            'id:239385',
            'id:257567',
            'id:257568',
            'id:351113',
            'id:614371',
            'id:633275',
            'id:660111',
            'id:1013699',
            'id:1013706',
            'id:1013707',
            'id:1013709',
            'id:1013713',
            'id:1013714',
            'id:1013715',
            'id:1013718',
            'id:1013719',
            'id:1013720',
            'id:1013728',
            'id:1013730',
            'id:1013731',
            'id:1046028',
            'id:1061861',
            'id:1061867',
            'id:1061873',
            'id:1061882',
            'id:1062782',
            'id:1062783',
            'id:1083630',
            'id:1094775',
            'id:1121125'
        ];

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Aktionsangebote')
            ->setUrl($fileNameInserted)
            ->setStoreNumber(implode(',', $aCampaignStores))
            ->setStart($strStart)
            ->setEnd($strEnd)
            ->setVisibleStart($strStart)
            ->setVariety('leaflet');

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
