<?php

/*
 * Dyn Flyer Crawler für Pflanzen Kölle (ID: 69974)
 */

class Crawler_Company_PflanzenKoelle_DynBrochure extends Crawler_Generic_Company
{
    private $localpath;

    /**
     * Use the crawl function to create a dynamic brochure in manual mode.
     * Adjust the variables as needed and run "php testcrawler.php Metro/DynBrochure 69631".
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Zend_Exception
     * @throws Zend_Log_Exception
     */

    protected $_brochureConfig;

    function crawl($companyId)
    {
        ini_set('memory_limit', '2G');
        $sTime = new Marktjagd_Service_Text_Times();

        // calculate brochure validity
        if (date('w') < 4) {
            $startDate = date('d.m.Y', strtotime('thursday last week'));
            $endDate = date('d.m.Y', strtotime('thursday this week'));
            $kw = $sTime->getWeekNr('last');
        } else {
            $startDate = date('d.m.Y', strtotime('thursday this week'));
            $endDate = date('d.m.Y', strtotime('thursday next week'));
            $kw = $sTime->getWeekNr();
        }

        $fileNameInserted = $this->buildDynBrochure($companyId);

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect($companyId);
        $sFtp->upload($fileNameInserted, './DynFly_PflanzenKoelle.pdf');

        # since PDM uploads the brochure manually, we are done after uploading the PDF to our server
        $this->_logger->info('Upload of new PDF succesful');
        $this->_response->setIsImport(false);
        $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
        return $this->_response;

//        $cBrochures = new Marktjagd_Collection_Api_Brochure();
//        $eBrochure = new Marktjagd_Entity_Api_Brochure();
//        $eBrochure->setTitle('Metro: Wochenangebote')
//            ->setBrochureNumber($kw . '_DynFly')
//            ->setVariety('leaflet')
//            ->setUrl($fileNameInserted)
//            ->setStart($startDate)
//            ->setVisibleStart($startDate)
//            ->setEnd($endDate);
//        $cBrochures->addElement($eBrochure);
//
//        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     *
     * Getting assets for dynamic flyer from FTP server
     *
     * @param int $companyId
     * @param array &$assets
     * @param array $images
     * @return string[][]
     * @throws Exception
     */
    private function downloadAssets(int $companyId, array &$assets): array
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->localpath = $sFtp->generateLocalDownloadFolder($companyId);

        $sFtp->connect($companyId);
        $sFtp->changedir($assets['brochure_template']['path']);
        $availableFiles = $sFtp->listFiles();

        $filtered = array_filter($availableFiles, function ($v) use ($assets) {
            return $v == $assets['brochure_template']['file_name'];
        });

        if (count($filtered) != 1) {
            $this->_logger->err('UNABLE to find asset: ' . $assets['brochure_template']['file_name']);
            $this->_logger->err('Available files: ' . implode(', ', $availableFiles));
            throw new Exception('FILE NOT FOUND');
        } else {
            $this->_logger->info('FOUND asset: ' . $assets['brochure_template']['file_name']);
            $assets['brochure_template']['path'] = $sFtp->downloadFtpToDir(array_pop($filtered), $this->localpath);
        }
        $filtered = array_filter($availableFiles, function ($v) use ($assets) {
            return $v == $assets['articles']['file_name'];
        });

        if (count($filtered) != 1) {
            $this->_logger->err('UNABLE to find asset: ' . $assets['articles']['file_name']);
            $this->_logger->err('Available files: ' . implode(', ', $availableFiles));
            throw new Exception('FILE NOT FOUND');
        } else {
            $this->_logger->info('FOUND asset: ' . $assets['articles']['file_name']);
            $assets['articles']['path'] = $sFtp->downloadFtpToDir(array_pop($filtered), $this->localpath);
        }

        $sFtp->close();

        return $assets;
    }

    /**
     * @param $brochureConfig
     * @return array
     * @throws Zend_Exception
     * @throws Zend_Log_Exception
     */
    private function getting_articles($companyId, &$images, &$assets): array
    {

        $sApi = new Marktjagd_Service_Input_MarktjagdApi;
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $allArticles = $sApi->findActiveArticlesByCompany($companyId);
        unset($allArticles['lastModified']);

        $articleUrls = $sPss->readFile($assets['articles']['path'], TRUE,null, 3)->getElement(0)->getData();
        $articleQueue = new SplQueue();
        foreach ($articleUrls as $singleArticle) {
            if(!isset($singleArticle['Artikel']) || $singleArticle['Artikel'] == '-' || empty($singleArticle['Artikel']))
                continue;
            $articleQueue->enqueue(trim($singleArticle['Artikel']));
        }

        $articles = [];
        foreach($allArticles as $article) {
            $apiArticle = $sApi->findArticleByArticleNumber($companyId,$article['articleNumber']);
            $articles[trim($apiArticle['url'])] = $apiArticle;
            $images[$article['articleNumber']] = $sHttp->getRemoteFile($apiArticle['image'], $this->localpath, $article['articleNumber'] . '.png');
        }

        $orderedArticles = [];
        for($articleQueue->rewind(); $articleQueue->valid(); $articleQueue->next()) {
            if(!isset($articles[$articleQueue->current()])) {
                $this->_logger->info('missing article :' . $articleQueue->current());
            }
            $orderedArticles[] = $articles[$articleQueue->current()];
        }

        $this->_logger->info(count($orderedArticles) . ' articles in Excel for dynamic flyer');

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
     * @return string - name of the created file
     * @throws Zend_Exception
     * @throws Zend_Log_Exception
     */
    public function buildDynBrochure(int $companyId): string
    {
        $assets = [
            'brochure_template' => [
                'file_name' => 'Template_DynFly_KW28.pdf',
                'path' => '.'
            ],
            'articles' => [
                'file_name' => 'Dynamischer_Flyer_Sommer_Artikel.xlsx',
                'path' => '.'
            ],
            'brochure_config' => [
                'products_per_page' => 6,
                'line_length_title' => 20,
                'line_length_description' => 22
            ]
        ];

        $images = [];

        $this->downloadAssets($companyId, $assets);

        $sPdf = new Marktjagd_Service_Output_Pdf();
        $aClickOutInfos = array_filter(
            $sPdf->getAnnotationInfos($assets['brochure_template']['path']),
            function ($v) {
                return
                    preg_match('#PLACEHOLDER.+#i', $v->url);
            });

        $pdfContent = [];
        $pdfClickouts = [];
        $articles = $this->getting_articles($companyId, $images, $assets);


        $idx = 1;
        foreach ($articles as $article) {

            $clickoutStartX = $aClickOutInfos[$idx]->rectangle->startX;
            $clickoutStartY = $aClickOutInfos[$idx]->rectangle->startY;
            $clickoutEndX = $aClickOutInfos[$idx]->rectangle->endX;
            $clickoutEndY = $aClickOutInfos[$idx]->rectangle->endY;
            $pageNumber = $aClickOutInfos[$idx]->page;


            $startX = $clickoutStartX + 10;
            $startY = $clickoutStartY + 60;
            $endX = $startX + 110;
            $endY = $startY + 110;

            // add image
            $pdfContent[] = [
                'page' => $pageNumber,
                'type' => 'image',
                'path' => $this->localpath . '/' .$article['number'] . '.png',
                'scaling' => TRUE,
                'startX' => $startX,
                'startY' => $startY,
                'endX' => $endX,
                'endY' => $endY
            ];


            // add clickout
            $pdfClickouts[] = [
                'page' => $pageNumber,
                'startX' => $clickoutStartX,
                'endX' => $clickoutEndX,
                'startY' => $clickoutStartY,
                'endY' => $clickoutEndY,
                'link' => $article['url']
            ];

            $article['price'] = '' . number_format($article['price'],2, ',', '');


            $xOffset = 0;
            // add price (before comma)
            if (strlen($article['price']) > 0) {
                $startX = $clickoutStartX + 130;
                $startY = $clickoutStartY + 160;

                $price = substr($article['price'], 0, strpos($article['price'], ','));
                switch (strlen($price)) {
                    case 3:
                        $xOffset = 30;
                        break;
                    case 2:
                        $xOffset = 14;
                        break;
                    default:
                        $xOffset = 0;
                        break;
                }
                $this->_logger->info('Preis '. $price . ' | Len:' . strlen($price) . ' | Offset: ' . $xOffset);

                $pdfContent[] = [
                    'page' => $pageNumber,
                    'startX' => $startX,
                    'startY' => $startY,
                    'type' => 'text',
                    'contents' => trim($price),
                    'font' => ['fontType' => 'CoreSlabM85Heavy', 'fontSize' => 28, 'fontColor' => '43|83|42']
                ];
            }

            // add price (after comma)
            if (strlen($article['price']) > 0) {
                $startX = $clickoutStartX + 150 + $xOffset;
                $startY = $clickoutStartY + 168;


                $pdfContent[] = [
                    'page' => $pageNumber,
                    'startX' => $startX,
                    'startY' => $startY,
                    'type' => 'text',
                    'contents' => substr($article['price'], strpos($article['price'],',') +1, strlen($article['price'])),
                    'font' => ['fontType' => 'CoreSlabM85Heavy', 'fontSize' => 16.32, 'fontColor' => '43|83|42']
                ];
            }

            // adding title
            $startX = $clickoutStartX + 130;
            $startY = $clickoutStartY + 149;


            $line = 0;
            $aTitle = [''];
            $aTitleOrig = preg_split('#\s+#', $article['title']);
            foreach ($aTitleOrig as $singleWord) {
                if (isset($aTitle[$line]) && strlen($aTitle[$line] . ' ' . $singleWord) > $assets['brochure_config']['line_length_title']) {
                    $line++;
                }
                $aTitle[$line] .= ' ' . $singleWord;
            }

            foreach ($aTitle as $key => $singleRow) {
                $pdfContent[] = [
                    'page' => $pageNumber,
                    'startX' => $startX,
                    'startY' => $startY - ($key * 15),
                    'type' => 'text',
                    'contents' => trim($singleRow),
                    'font' => ['fontType' => 'CoreSlabM65Bold', 'fontSize' => 12, 'fontColor' => '43|83|42']
                ];
            }

            // adding article desciption
            $startX = $clickoutStartX + 130;
            $startY = $clickoutStartY + 136 - ($key * 15);

            $line = 0;
            $aTitle = [''];
            $aTitleOrig = preg_split('#\s+#', $article['description']);
            foreach ($aTitleOrig as $singleWord) {
                if (isset($aTitle[$line]) && strlen($aTitle[$line] . ' ' . $singleWord) > $assets['brochure_config']['line_length_description']) {
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
                    'font' => ['fontType' => 'CoreSans', 'fontSize' => 12, 'fontColor' => '0|0|0']
                ];

            }
            $idx++;
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

        $fileNameInserted =
            $sPdf->addElements(
                $brochureTemplate
                , $jsonFilePath);

        $fileNameInserted = $sPdf->setAnnotations(
            $fileNameInserted,
            $jsonFileClickouts);

        Zend_Debug::dump($fileNameInserted);die;

        return $fileNameInserted;
    }
}
