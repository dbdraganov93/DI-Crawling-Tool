<?php

/*
 * Dyn Flyer Crawler für BabyOne (ID: 73170)
 */

class Crawler_Company_BabyOneAt_DynBrochure extends Crawler_Generic_Company
{
    /**
     * Use the crawl function to create a dynamic brochure in manual mode.
     * Adjust the variables as needed and run "php testcrawler.php BabyOne/DynBrochure 28698".
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Zend_Exception
     * @throws Zend_Log_Exception
     */
    function crawl($companyId)
    {

        $startDate = '14.02.2021';
        $endDate = '21.02.2021';
        $articleFileName = '08.02. - 21.02. dynamisch Artikelliste.xlsx';
        $coverPageFileName = 'eprsopekt_lockdown_woche3und4_a4_sas_original.pdf';

        $fileNameInserted = $this->buildDynBrochure($companyId, $articleFileName, $coverPageFileName);

        $s3 = new Marktjagd_Service_Output_S3File('mjcsv', 'test_babyone_title_only.pdf');
        var_dump($s3->saveFileInS3($fileNameInserted));

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle('BabyOne: Sicherheit zuhause!')
            ->setBrochureNumber('KW 07 (dynamisch)')
            ->setVariety('leaflet')
            ->setUrl($fileNameInserted)
            ->setStart($startDate)
            ->setVisibleStart($startDate)
            ->setEnd($endDate);
        $cBrochures->addElement($eBrochure);
        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     *
     * Getting assets for dynamic flyer from FTP server
     *
     * @param int $companyId
     * @param array &$assetConfig
     * @return string[][]
     * @throws Exception
     */
    private function getting_assets(int $companyId, array &$assets): array
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $sFtp->connect('28698');
        foreach ($assets as &$necessaryAsset) {
            $sFtp->changedir($necessaryAsset['path']);
            $availableFiles = $sFtp->listFiles();
             $filtered = array_filter($availableFiles, function ( $v ) use ( $necessaryAsset ) {
                return $v == $necessaryAsset['file_name'];
             });

             if ( count($filtered) != 1) {
                 $this->_logger->err('UNABLE to find asset: ' . $necessaryAsset['file_name']);
                 $this->_logger->err('Available files: ' . implode(', ', $availableFiles));
                 throw new Exception('FILE NOT FOUND');
             } else {
                 $this->_logger->info('FOUND asset: ' . $necessaryAsset['file_name']);
                 $necessaryAsset['path'] = $sFtp->downloadFtpToDir(array_pop($filtered), $localPath);
             }
             $sFtp->changedir('..');
        }

        $sFtp->close();
        return $assets;
    }

    /**
     * @param $path
     * @param int $companyId
     * @return array
     * @throws Zend_Exception
     * @throws Zend_Log_Exception
     */
    private function getting_articles($path, int $companyId, $brochureConfig): array
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi;
        $sSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();
        $articlesFile = $sSpreadsheet->readFile($path, FALSE)->getElement(0)->getData();

        $articles = [];
        $this->_logger->info(count($articlesFile) . ' articles in Excel for dynamic flyer');
        foreach ($articlesFile as $articleRow) {

            [$articleRow['Kategorie'], , ,$articleRow['Artikelnummer']] = explode(';', $articleRow[1]);

            $article = $sApi->findArticleByArticleNumber($companyId, $articleRow['Artikelnummer']);
            if ($article == false) {
                $this->_logger->err('UNABLE to find article with articleNumber: ' . $articleRow['Artikelnummer']);
                continue;
            } else {
                $this->_logger->info('FOUND article ' . $article['number'] . ': ' . $article['title']);
                $article['category'] = $articleRow['Kategorie'];
            }

            $tag = $sApi->findManufacturerTagByArticleId($companyId, $article['id']);
            if ($tag == false) {
                $this->_logger->err('UNABLE to find manufacturer for articleNumber: ' . $article['article_number']);
            } else {
                $this->_logger->info('FOUND manufacturer for ' . $article['number'] . ': ' . (string) $tag);
                $article['manufacturer'] = $tag;
            }
            $articles[] = $article;
        }

        $this->_logger->info(count($articles) . ' articles from csv for dynamic flyer found in our backend');
        if (count($articlesFile) != count($articles)) {
            $this->_logger->err('Amount of found articles does not match input data.');
        }

        $this->_logger->info('Reording articles based on brochure pages and categories');

        $orderedArticles = array();
        foreach ($brochureConfig['categories'] as $category) {
            array_push(
                $orderedArticles,
                array_filter($articles, function ( $v ) use ( $category ) {
                    return preg_match('#' . $category . '#', $v['category']);
                })
            );
        }
        return $orderedArticles;
    }

    /**
     * This function creates the dynamic brochure pdf and returns name+path of the file.
     * You can use this function inside this crawler script or outside of it to just create
     * the pdf without importing it directly.
     *
     * ! You must upload the article file and the cover page to our FTP server !
     *
     * @param int $companyId
     * @param string $articleFileName - name of the article .xlsx file
     * @param string $coverPageFileName - name of the coverPage .pdf file
     * @return string - name of the created file
     * @throws Zend_Exception
     * @throws Zend_Log_Exception
     */
    public function buildDynBrochure(int $companyId, string $articleFileName, string $coverPageFileName): string
    {
        $assets = array(
            'cover_page' => array(
                'file_name' => $coverPageFileName,
                'path' => 'Flyer AT'
            ),
            'brochure_template' => array(
                'file_name' => 'BabyOne_template_without_title.pdf',
                'path' => 'dynamic_flyer_and_discover'
            ),
            'clickout_template' => array(
                'file_name' => 'BabyOne_clickout_template.pdf',
                'path' => 'dynamic_flyer_and_discover'
            ),
            'articles' => array(
                'file_name' => $articleFileName,
                'path' => 'Flyer AT'
            ),
        );


        $brochureConfig = array(
            // The order of categories represents the order of pages in the brochure as well
            // Meaning, 'Autositze & Unterwegs' - products are on the first content page
            'categories' => array(
                'Kinderwagen',
                'Autositze',
                'Zuhause',
                'Textil&Spielware'
            ),
            'products_per_page' => 6,
            'line_length_title' => 20,
            'line_length_description' => 30
        );

        $this->getting_assets($companyId, $assets);

        $sPdf = new Marktjagd_Service_Output_Pdf();
        $aClickOutInfos = array_filter(
            $sPdf->getAnnotationInfos($assets['clickout_template']['path']),
            function ($v) {
                return
                    $v->page == 1 &&
                    preg_match('#PLACEHOLDER.+#', $v->url);
            });

        $pdfContent = [];
        $pdfClickouts = [];
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $pagesOfArticles = $this->getting_articles($assets['articles']['path'], $companyId, $brochureConfig);
        foreach ($pagesOfArticles as $pageNumber => $pageOfArticles) {
            $idx = 1;
            foreach ($pageOfArticles as $articleNumber => $article) {
                $localImagePath = $sHttp->getRemoteFile($article['image'], $localPath, $article['number'] . '.jpg');

                $clickoutStartX = $aClickOutInfos[$idx]->rectangle->startX;
                $clickoutStartY = $aClickOutInfos[$idx]->rectangle->startY;

                if ($idx == 2 || $idx == 3) {
                    $clickoutStartY -= 1;
                } elseif ($idx == 4 || $idx == 5) {
                    $clickoutStartY -= 3;
                }

                $startX = $clickoutStartX + 10;
                $startY = $clickoutStartY + 60;
                $endX = $startX + 110;
                $endY = $startY + 110;

                $pdfContent[] = [
                    'page' => $pageNumber,# + 1,
                    'type' => 'image',
                    'path' => $localImagePath,
                    'scaling' => TRUE,
                    'startX' => $startX,
                    'startY' => $startY,
                    'endX' => $endX,
                    'endY' => $endY
                ];

                // adding clickout
                $startX = $clickoutStartX + 162;
                $startY = $clickoutStartY + 100;

                $pdfClickouts[] = [
                    'page' => $pageNumber,# + 1,
                    'startX' => $startX,
                    'endX' => $startX + 20,
                    'startY' => $startY,
                    'endY' => $startY + 20,
                    'link' => $article['url']
                ];

                // adding price
                $startX = $clickoutStartX + 162;
                $startY = $clickoutStartY + 35;

                $price = number_format($article['price'], 2, ',', '');

                if (strlen($price) == 7) {
                    $startX = $startX - 5;
                } elseif (strlen($price) == 5) {
                    $startX = $startX + 5;
                } elseif (strlen($price) == 4) {
                    $startX = $startX + 10;
                }

                $pdfContent[] = [
                    'page' => $pageNumber,# + 1,
                    'startX' => $startX,
                    'startY' => $startY,
                    'type' => 'text',
                    'contents' => trim($price . ' €'),
                    'font' => ['fontType' => 'ProximaNovaCondSemibold', 'fontSize' => 16, 'fontColor' => '255|255|255']
                ];

                // adding original price
                if (strlen($article['manufacturer_price']) > 0) {
                    $startX = $clickoutStartX + 200;
                    $startY = $clickoutStartY + 60;

                    $price = number_format($article['manufacturer_price'], 2, ',', '');

                    if (strlen($price) == 7) {
                        $startX = $startX - 5;
                    } elseif (strlen($price) == 5) {
                        $startX = $startX + 5;
                    } elseif (strlen($price) == 4) {
                        $startX = $startX + 10;
                    }

                    $pdfContent[] = [
                        'page' => $pageNumber,# + 1,
                        'startX' => $startX,
                        'startY' => $startY,
                        'type' => 'text',
                        'contents' => trim($price . ' €'),
                        'font' => ['fontType' => 'ProximaNovaCondRegular', 'fontSize' => 12, 'fontColor' => '67|67|67']
                    ];

                    $startX = $startX - 2;
                    $startY = $startY - 2;

                    $endX = $startX + 40;
                    $endY = $startY + 10;

                    $pdfContent[] = [
                        'page' => $pageNumber,# + 1,
                        'startX' => $startX,
                        'startY' => $startY,
                        'endX' => $endX,
                        'endY' => $endY,
                        'type' => 'line',
                        'line' => ['lineWidth' => 0.5, 'lineColor' => '216|0|21']
                    ];
                }

                // adding manufacturer
                $startX = $clickoutStartX + 130;
                $startY = $clickoutStartY + 160;

                $line = 0;
                $aTitle = [''];
                $aTitleOrig = preg_split('#\s+#', $article['manufacturer']);
                foreach ($aTitleOrig as $singleWord) {
                    if (isset($aTitle[$line]) && strlen($aTitle[$line] . ' ' . $singleWord) > $brochureConfig['line_length_title']) {
                        $line++;
                    }
                    $aTitle[$line] .= ' ' . $singleWord;
                }

                foreach ($aTitle as $key => $singleRow) {
                    $pdfContent[] = [
                        'page' => $pageNumber,# + 1,
                        'startX' => $startX,
                        'startY' => $startY - ($key * 15),
                        'type' => 'text',
                        'contents' => trim($singleRow),
                        'font' => ['fontType' => 'ProximaNovaCondRegular', 'fontSize' => 13, 'fontColor' => '67|67|67']
                    ];
                }

                // adding title
                $startX = $clickoutStartX + 130;
                $startY = $clickoutStartY + 145;

                $line = 0;
                $aTitle = [''];
                $aTitleOrig = preg_split('#\s+#', $article['title']);
                foreach ($aTitleOrig as $singleWord) {
                    if (isset($aTitle[$line]) && strlen($aTitle[$line] . ' ' . $singleWord) > $brochureConfig['line_length_title']) {
                        $line++;
                    }
                    $aTitle[$line] .= ' ' . $singleWord;
                }

                foreach ($aTitle as $key => $singleRow) {
                    $pdfContent[] = [
                        'page' => $pageNumber,# + 1,
                        'startX' => $startX,
                        'startY' => $startY - ($key * 15),
                        'type' => 'text',
                        'contents' => trim($singleRow),
                        'font' => ['fontType' => 'ProximaNovaCondSemibold', 'fontSize' => 13, 'fontColor' => '0|0|0']
                    ];
                }
                $idx += 1;
            }
        }

        $jsonFilePath = APPLICATION_PATH . '/../public/files/tmp/content.json';
        $fh = fopen($jsonFilePath, 'w+');
        fwrite($fh, json_encode($pdfContent));
        fclose($fh);

        $jsonFileClickouts = APPLICATION_PATH . '/../public/files/tmp/clickouts.json';
        $fh = fopen($jsonFileClickouts, 'w+');
        fwrite($fh, json_encode($pdfClickouts));
        fclose($fh);

        $brochureTemplate = $assets['brochure_template']['path'];
        $fileNameInserted = $sPdf->addElements(
            $brochureTemplate,
            $jsonFilePath);

        $fileNameInserted = $sPdf->setAnnotations(
            $fileNameInserted,
            $jsonFileClickouts);
        return $sPdf->merge([$assets['cover_page']['path'] ,$fileNameInserted], APPLICATION_PATH . '/../public/files/tmp/');
    }
}
