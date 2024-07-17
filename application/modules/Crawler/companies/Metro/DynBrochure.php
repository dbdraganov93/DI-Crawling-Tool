<?php

/*
 * Dyn Flyer Crawler für Metro (ID: 69631)
 */

class Crawler_Company_Metro_DynBrochure extends Crawler_Generic_Company
{
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
        $sFtp->upload($fileNameInserted, './DynFly_Metro.pdf');

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
    private function getting_assets(int $companyId, array &$assets, array &$images): array
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $sFtp->connect($companyId);
        foreach ($sFtp->listFiles('.', '#\.(png|jpg)#', false) as $singleImage) {
            $images[$singleImage] = $sFtp->downloadFtpToDir($singleImage, $localPath);
        }
        foreach ($assets as $assetName => &$necessaryAsset) {
            $sFtp->changedir($necessaryAsset['path']);
            $availableFiles = $sFtp->listFiles();
            $filtered = array_filter($availableFiles, function ($v) use ($necessaryAsset) {
                return $v == $necessaryAsset['file_name'];
            });

            if (count($filtered) != 1) {
                    if($necessaryAsset['file_name'] == 'header.pdf') {
                        continue;
                    }
                $this->_logger->err('UNABLE to find asset: ' . $necessaryAsset['file_name']);
                $this->_logger->err('Available files: ' . implode(', ', $availableFiles));
                throw new Exception('FILE NOT FOUND');
            } else {
                $this->_logger->info('FOUND asset: ' . $necessaryAsset['file_name']);
                $necessaryAsset['path'] = $sFtp->downloadFtpToDir(array_pop($filtered), $localPath);
            }
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
    private function getting_articles(&$brochureConfig, &$assets, &$images): array
    {
        $sGSheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $week = 'this';
        $gSheetId = '1bgsVsuZS3ZKYKUkVRNzn2t0WpOI427eAXvfbbFlLQvo';
        $this->_logger->info('reading from sheet : Produktliste_KW' . date('W', strtotime($week . ' week')));
        $articlesFile = $sGSheet->getFormattedInfos($gSheetId, 'A1', 'Z', 'Produktliste_KW' . date('W', strtotime($week . ' week')));

        $localPath = $sFtp->connect('69631', TRUE);

        $categories = [];
        foreach ($articlesFile as $articleRow) {
            if (!$articleRow['Produktname']) {
                continue;
            }
            $categories[$articleRow['Warenkorb-Nr.']] = $articleRow['Warenkorb-Titel'];
        }
        ksort($categories);
        $brochureConfig['categories'] = array_values(array_unique($categories));
        unset($categories);

        $articles = [];
        $this->_logger->info(count($articlesFile) . ' articles in Excel for dynamic flyer');
        foreach ($articlesFile as $articleRow) {
            if (!$articleRow['Produktname']) {
                continue;
            }

            $logo = NULL;
            if (strlen($articleRow['Logo/Zusatz'])) {
                $logo = $articleRow['Logo/Zusatz'] . '.png';
            }
            $articles[$articleRow['Warenkorb-Nr.'] * 1000 + $articleRow['Seitennummer'] * 100 + $articleRow['Produktreihenfolge']] = [
                'title' => trim($articleRow['Produktname']),
                'category' => $articleRow['Warenkorb-Titel'],
                'categoryNo' => $articleRow['Warenkorb-Nr.'],
                'text' => $articleRow['Produktbeschreibung'],
                'price' => $articleRow['Preis Netto'],
                'priceInclVat' => $articleRow['Preis Brutto'],
                'number' => $articleRow['Artikelnummer'],
                'manufacturer_price' => $articleRow['Streichpreis (optional)'],
                'url' => $articleRow['Tracking-Link inkl. URL'],
                'image' => $this->getImage($articleRow['Artikelnummer']),
                'headerImage' => $sFtp->downloadFtpToDir($articleRow['Warenkorb-Bild'], $localPath),
                'logo' => $logo,
                'validEnd' => $articleRow['Gültigkeit bis'] ? 'gültig bis ' . $articleRow['Gültigkeit bis'] : '',
                'page' => $articleRow['Seitennummer'],
                'sort' => $articleRow['Produktreihenfolge'] - 1
            ];
        }
        $sFtp->close();
        $assets['brochure_template']['path'] = $this->preparePages($articles, $assets, $images, $brochureConfig);

        ksort($articles);

        $orderedArticles = [];
        foreach ($brochureConfig['categories'] as $categoryNumber => $category) {
            $filteredArray = array_filter($articles, function ($v) use ($category) {
                return preg_match('#' . $category . '#', $v['category']);
            });
            foreach ($filteredArray as $singleItem) {
                $orderedArticles[($categoryNumber + 1) * 10 + intval($singleItem['sort'] / 6)][] = $singleItem;
            }
        }
        ksort($orderedArticles);

        return array_values($orderedArticles);
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
        $assets = array(
            'brochure_template' => array(
                'file_name' => 'Metro_clickout_template.pdf',
                'path' => '.'
            ),
            'clickout_template' => array(
                'file_name' => 'Metro_clickout_template.pdf',
                'path' => '.'
            ),
            'header' => array(
                'file_name' => 'header.pdf',
                'path' => '.'
            ),

        );

        $images = [];


        $brochureConfig = [
            'products_per_page' => 6,
            'line_length_title' => 18,
            'line_length_description' => 29
        ];
        $this->getting_assets($companyId, $assets, $images);

        $sPdf = new Marktjagd_Service_Output_Pdf();
        $aClickOutInfos = array_filter(
            $sPdf->getAnnotationInfos($assets['clickout_template']['path']),
            function ($v) {
                return
                    $v->page == 0 &&
                    preg_match('#PLACEHOLDER.+#', $v->url);
            });

        sort($aClickOutInfos);

        $pdfContent = [];
        $pdfClickouts = [];
        $emptyFields = [];
        $pagesOfArticles = $this->getting_articles($brochureConfig, $assets, $images);

        foreach ($pagesOfArticles as $pageNumber => $pageOfArticles) {
            $emptyFields[$pageNumber] = $aClickOutInfos;
            $idx = 0;
            foreach ($pageOfArticles as $articleNumber => $article) {
                $clickoutStartX = $aClickOutInfos[$idx]->rectangle->startX;
                $clickoutStartY = $aClickOutInfos[$idx]->rectangle->startY;
                $clickoutEndX = $aClickOutInfos[$idx]->rectangle->endX;
                $clickoutEndY = $aClickOutInfos[$idx]->rectangle->endY;
                unset($emptyFields[$pageNumber][$idx]);

                if ($idx == 2 || $idx == 3) {
                    $clickoutStartY -= 1;
                } elseif ($idx == 4 || $idx == 5) {
                    $clickoutStartY -= 3;
                }

                $startX = $clickoutStartX + 10;
                $startY = $clickoutStartY + 60;
                $endX = $startX + 110;
                $endY = $startY + 110;

                // add image
                $pdfContent[] = [
                    'page' => $pageNumber,# + 1,
                    'type' => 'image',
                    'path' => $article['image'],
                    'scaling' => TRUE,
                    'startX' => $startX,
                    'startY' => $startY,
                    'endX' => $endX,
                    'endY' => $endY
                ];

                // add logo, if it exists
                if (strlen($article['logo']) > 0) {

                    $startX = $clickoutStartX + 20;
                    $startY = $clickoutStartY + 35;

                    if (preg_match('#dauerhaft#', $images[$article['logo']])) {
                        $startX = $clickoutStartX + 10;
                        $startY = $clickoutStartY + 20;
                    }

                    $endX = $startX + 50;
                    $endY = $startY + 50;

                    $pdfContent[] = [
                        'page' => $pageNumber,# + 1,
                        'type' => 'image',
                        'path' => $images[$article['logo']],
                        'scaling' => TRUE,
                        'startX' => $startX,
                        'startY' => $startY,
                        'endX' => $endX,
                        'endY' => $endY
                    ];
                }

                // adding clickout
                $pdfClickouts[] = [
                    'page' => $pageNumber,# + 1,
                    'startX' => $clickoutStartX,
                    'endX' => $clickoutEndX,
                    'startY' => $clickoutStartY,
                    'endY' => $clickoutEndY,
                    'link' => $article['url']
                ];

                // adding price
                if (strlen($article['price']) > 0) {
                    $startX = $clickoutStartX + 162;
                    $startY = $clickoutStartY + 38;

                    $price = $article['price'];

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
                        'contents' => trim($price . ' €*'),
                        'font' => ['fontType' => 'ProximaNovaCondSemibold', 'fontSize' => 16, 'fontColor' => '255|255|255']
                    ];
                }

                // adding price including VAT
                if (strlen($article['priceInclVat']) > 0) {
                    $startX = $clickoutStartX + 182;
                    $startY = $clickoutStartY + 30;

                    $price = $article['priceInclVat'];

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
                        'contents' => trim('(' . $price . ' €)'),
                        'font' => ['fontType' => 'ProximaNovaCondSemibold', 'fontSize' => 8, 'fontColor' => '255|255|255']
                    ];
                }

                // adding valid end
                if (strlen($article['validEnd']) > 0) {
                    $startX = $clickoutStartX + 144;
                    $startY = $clickoutStartY + 12;

                    $pdfContent[] = [
                        'page' => $pageNumber,# + 1,
                        'startX' => $startX,
                        'startY' => $startY,
                        'type' => 'text',
                        'contents' => $article['validEnd'],
                        'font' => ['fontType' => 'ProximaNovaCondRegular', 'fontSize' => 11, 'fontColor' => '67|67|67']
                    ];
                }

                // adding title
                $startX = $clickoutStartX + 130;
                $startY = $clickoutStartY + 160;


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

                // adding article desciption
                $startX = $clickoutStartX + 130;
                $startY = $clickoutStartY + 145 - ($key * 15);

                $line = 0;
                $aTitle = [''];
                $aTitleOrig = preg_split('#\s+#', $article['text']);
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

                $idx += 1;
            }
        }

        $canvas = imagecreatetruecolor(10, 10);
        $color = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $color);
        imagepng($canvas, APPLICATION_PATH . '/../public/files/tmp/white.png');
        imagedestroy($canvas);
        $placeholderImage = APPLICATION_PATH . '/../public/files/tmp/white.png';
        //remove unused article fields
        foreach ($emptyFields as $pageNr => $fieldPage) {
            foreach ($fieldPage as $emptyArticleField) {
                $pdfContent[] = [
                    'page' => $pageNr,
                    'type' => 'image',
                    'path' => $placeholderImage,
                    'scaling' => FALSE,
                    'startX' => $emptyArticleField->rectangle->startX - 2,
                    'startY' => $emptyArticleField->rectangle->startY - 2,
                    'endX' => $emptyArticleField->rectangle->endX + 2,
                    'endY' => $emptyArticleField->rectangle->endY + 2
                ];
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

        $fileNameInserted =
            $sPdf->addElements(
                $brochureTemplate
                , $jsonFilePath);

        $fileNameInserted = $sPdf->setAnnotations(
            $fileNameInserted,
            $jsonFileClickouts);

        if($assets['header']['path'] != '.') {
            $fileNameMerged = $sPdf->merge([$assets['header']['path'], $fileNameInserted], APPLICATION_PATH . '/../public/files/tmp/');
            rename($fileNameMerged, APPLICATION_PATH . '/../public/files/tmp/DynFly_Metro.pdf');
        }
        else {
            rename($fileNameInserted, APPLICATION_PATH . '/../public/files/tmp/DynFly_Metro.pdf');
        }
        $fileNameMerged = APPLICATION_PATH . '/../public/files/tmp/DynFly_Metro.pdf';
        return $fileNameMerged;
    }

    private function getImage(string $articleNumber): string
    {
        for ($j = 1; $j < 50; $j++) {
            for ($i = 1; $i < 10; $i++) {
                $remoteImage = 'https://cdn.metro-group.com/de/de_pim_' . $articleNumber . '00' . $j . '_0' . $i . '.png';
                if ($articleNumber == 688309001 || $articleNumber == 749589001 || $articleNumber == 302414001) {
                    $remoteImage = 'https://cdn.metro-group.com/de/de_pim_' . $articleNumber . '002_0' . $i . '.png';
                }
                $this->_logger->info('getting ' . $remoteImage);
                $sHttp = new Marktjagd_Service_Transfer_Http();
                $res = $sHttp->getRemoteFile($remoteImage, APPLICATION_PATH . '/../public/files/tmp/', $articleNumber . '.png');
                if ($res) {
                    return $res;
                }
            }
        }
        throw new Exception('Article number ' . $articleNumber . ': cannot get image');
    }

    private function preparePages(array $articles, array &$assets, array &$images, array $brochureConfig): string
    {
        $categories = [];
        $imagesHeader = [];
        $categoryPage = [];
        foreach ($articles as $article) {
            $article['price'] = floatval(str_replace(',', '.', $article['price']));

            if (!isset($categories[$article['category']])) {
                $categories[$article['category']] = 1;
                $imagesHeader[$article['category']] = $article['headerImage'];
                $categoryPage[$article['category']] = $article['categoryNo'];
            } else {
                $categories[$article['category']]++;
            }

        }

        $pages = [];
        foreach ($categories as $categoryName => $categoryArticles) {
            $pages[$categoryPage[$categoryName]] = [
                'categoryName' => $categoryName
            ];

            if ($categoryArticles > $brochureConfig['products_per_page']) {
                for ($i = 1; $i < ($categoryArticles / $brochureConfig['products_per_page']); $i++) {
                    $pages[$categoryPage[$categoryName] . '|' . $i] = [
                        'categoryName' => $categoryName
                    ];
                }
            }
        }
        ksort($pages);
        $pages = array_values($pages);

        $sPdf = new Marktjagd_Service_Output_Pdf();
        $mergeList = [];
        foreach ($pages as $pageNumber => $page) {
            $pageNumber += 1;
            copy($assets['brochure_template']['path'], APPLICATION_PATH . '/../public/files/tmp/' . $pageNumber . '.pdf');

            $pdfContent = [];
            // add header image
            if (isset($imagesHeader[$page['categoryName']])) {
                $pdfContent[] = [
                    'page' => 0,
                    'type' => 'image',
                    'path' => $imagesHeader[$page['categoryName']],
                    'scaling' => TRUE,
                    'startX' => 0,
                    'startY' => 679.164,
                    'endX' => 595.073,
                    'endY' => 842.04
                ];
            }

            // add header text
            $pdfContent[] = [
                'page' => 0,
                'startX' => 300 - (strlen('Sichern Sie sich unsere Artikel zum Thema "' . $page['categoryName']) * 3 . '"'),
                'startY' => 650,
                'type' => 'text',
                'contents' => 'Sichern Sie sich unsere Artikel zum Thema "' . $page['categoryName'] . '"',
                'font' => ['fontType' => 'ProximaNovaCondSemibold', 'fontSize' => 16, 'fontColor' => '0|0|0']
            ];

            $jsonFilePath = APPLICATION_PATH . '/../public/files/tmp/headers.json';
            $fh = fopen($jsonFilePath, 'w+');
            fwrite($fh, json_encode($pdfContent));
            fclose($fh);

            $mergeList[] = $sPdf->addElements(
                APPLICATION_PATH . '/../public/files/tmp/' . $pageNumber . '.pdf',
                $jsonFilePath);
        }
        return $sPdf->merge($mergeList, APPLICATION_PATH . '/../public/files/tmp/');
    }
}
