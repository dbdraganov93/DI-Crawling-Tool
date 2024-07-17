<?php

/**
 * Dynamic Brochure Crawler für KiK (ID: 340)
 */

class Crawler_Company_Kik_DynBrochure extends Crawler_Generic_Company
{

    # modify these as needed
    private string $countryCode;
    private string $articleFile;
    private string $brochureFilePrefix;
    private string $brochureFile;
    private string $configFile;
    private array $countries = [];
    private string $ftpPath;
    private Marktjagd_Service_Input_PhpSpreadsheet $sPss;
    private Marktjagd_Service_Output_Pdf $sPdf;
    private Marktjagd_Service_Transfer_FtpMarktjagd $sFtp;

    # array to get currency symbols and the correct Excel columns
    private const aCurrency = [
        'DE' => ' €',
        'AT' => ' €',
        'NL' => ' €',
        'IT' => ' €',
        'SI' => ' €',
        'CZ' => ' Kč',
        'SK' => ' €',
        'HU' => ' Ft',
        'HR' => ' kn',
        'PL' => ' zł',
        'RO' => ' LEI',
        'BG' => ' лв.',
    ];

    # path to download the images
    private const localPath = APPLICATION_PATH . '/../public/files/ftp/340/';
    # path to the pdf template and to save the result pdf
    private const tmpFilePath = APPLICATION_PATH . '/../public/files/tmp/';

    public function crawl($companyId)
    {
        $this->initConfig();
        $this->createMissingImagePlaceholder();
        $aArticleImages = $this->downloadImages($companyId);

        foreach($this->countries as $country) {
            $this->countryCode = $country;

            $this->initCountry();
            $aProductPlaces = $this->buildTemplate();
            $aArticlesToSet = $this->getArticlesToSet();

            # test, if images are missing (wrong configuration in Excel sheet)
            $this->checkForMissingImages($aArticlesToSet, $aArticleImages);

            $pageElements = $this->fillTemplateWithArticles($aProductPlaces, $aArticlesToSet);
            $fileNameLinked = $this->buildPdf($pageElements);

            Zend_Debug::dump($fileNameLinked);
        }
        die;
    }

    private function initConfig() {
        # get the services
        $this->sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $this->sPdf = new Marktjagd_Service_Output_Pdf();

        #set the memory limit
        ini_set('memory_limit', '4G');
        # if the directory exists, delete and recreate it
        if (is_dir($this::localPath)) {
            exec('rm -r ' . $this::localPath);
        }

        #download the asset files from folder "/Kik"
        $this->sFtp->connect('Kik', FALSE);
        $this->sFtp->download('DynFly_Config.xlsx', $this::tmpFilePath . 'DynFly_Config.xlsx');
        $this->configFile = $this::tmpFilePath . 'DynFly_Config.xlsx';
        $parameters = $this->sPss->readFile($this->configFile, TRUE)->getElement(0)->getData();
        foreach($parameters as $parameter) {
                if($parameter['Parameter name'] == 'brochureFile') $this->brochureFilePrefix = $parameter['Parameter value'];
                else if($parameter['Parameter name'] == 'ftpPath') $this->ftpPath = $parameter['Parameter value'];
                else if(preg_match('#country(?<countryCode>\w{2})#',$parameter['Parameter name'], $countryMatch) && $parameter['Parameter value'] == 'true') $this->countries[] = $countryMatch['countryCode'];
        }
        $this->_logger->info('Configuration successfully loaded from FTP server.');
    }

    private function initCountry() {
        $this->sFtp->connect('Kik', FALSE);
        $this->sFtp->download('Artikelliste_'. $this->countryCode .'.xlsx', $this::tmpFilePath. 'Artikelliste_'. $this->countryCode .'.xlsx');
        $this->articleFile = $this::tmpFilePath. 'Artikelliste_'. $this->countryCode .'.xlsx';
        $this->sFtp->download($this->brochureFilePrefix . $this->countryCode .'.pdf', $this::tmpFilePath. $this->brochureFilePrefix . $this->countryCode .'.pdf');
        $this->brochureFile = $this::tmpFilePath. $this->brochureFilePrefix . $this->countryCode .'.pdf';
    }

    /**
     * This method traverses the FTP-Folder and extracts images and the article list.
     * The files are downloaded to the localpath provided in the crawler.
     *
     * @param int $companyId
     * @return array[] $aArticleImages
     * @throws Exception
     */
    public function downloadImages(int $companyId): array
    {
        $ftpPaths = [
            'Logos/Lizenzlogos',
            'Logos/Ökotex Button',
            'Logos/Pack_Einklinker_PNGs_alle Länder',
            'Logos/Der Preis spricht für sich',
            'Logos/Silberpreise',
            $this->ftpPath
        ];

        $this->sFtp->connect($companyId, TRUE);

        $aArticleImages = [];

        # traverse all folders in ftpPaths array
        foreach ($ftpPaths as $ftpPath) {
            if (!$this->sFtp->changedir($ftpPath)) {
                throw new Exception('Cannot change to sub-dir ' . $ftpPath);
            }

            # download every image there
            foreach ($this->sFtp->listFiles('.', '#.*#', true) as $singleFile) {

                if (preg_match('#\.(png|jpg|jpeg)$#', $singleFile, $imageMatch)) {
                    $aArticleImages[preg_replace('#\s#', '_', $singleFile)] = $this->sFtp->downloadFtpToDir($singleFile, $this::localPath);

                    # to debug uncomment here
                    #$this->_logger->info('Downloading ' . $singleFile);
                }
            }
            $this->sFtp->changedir('../..');
        }

        $this->_logger->info('Image download completed');
        return $aArticleImages;
    }

    /**
     * This function reads all annotations from the template pdf and builds a template array
     * that can be filled with articles
     *
     * @return array[]
     * @throws Exception
     */
    public function buildTemplate(): array
    {
        # get links from template
        $aAnnots = $this->sPdf->getAnnotationInfos($this->brochureFile);
        $pageLayout = $this->sPss->readFile($this->configFile, TRUE)->getElement(1)->getData();
        # build the placeholder list
        $aProductPlaces = [];
        for ($i = 1; $i <= 50; $i++) {
            $aProductPlaces[] = [$i => []];
        }
        foreach ($aAnnots as $annotation) {
            if (!preg_match('#PLACEHOLDER_(\d)+#', $annotation->url)) {
                unset($annotation);
                continue;
            }
            foreach($pageLayout as $pageLayoutRow) {
                if($annotation->page == $pageLayoutRow['Page number']) $annotation->category = $pageLayoutRow['Category'];
            }
            $aProductPlaces[$annotation->page][$annotation->url] = $annotation;
            ksort($aProductPlaces[$annotation->page]);
        }

        return $aProductPlaces;
    }

    /**
     * This function reads all articles from the local article file and returns
     * an array with them
     *
     * @return array[] - The list of articles to add to the brochure
     */
    public function getArticlesToSet(): array
    {
        $aArticles = [];
        # get article definition from article file
        $aData = $this->sPss->readFile($this->articleFile, TRUE)->getElement(0)->getData();


        foreach ($aData as $rowNr => $singleRow) {
            if (!$singleRow['Bild URL'] || !$singleRow['Produktname'] || $singleRow['Preis aktuell'] == '#REF!' || $singleRow['Preis aktuell'] == '#N/A' || $singleRow['Produktname'] == '#N/A' || preg_match('#dopplung|titel|0|moodseite|Heroartikel|highlightseite#i', $singleRow['Kategorie'])) {
                continue;
            }

            foreach ($singleRow as $columnName => $columnValue) {
                if ($columnValue == '0')
                    $singleRow[$columnName] = NULL;

                # fix a common spelling mistake
                if (preg_match('#logo.*zusatz#i', $columnName))
                    $singleRow['Logo/Zusatz'] = $singleRow[$columnName];
            }

            $this->addArticleToList($singleRow, $aArticles, 'Bild URL');

            # if there is more than 1 variant of the article, add it as new product
            for ($i = 2; $i < 10; $i++) {
                if (!empty($singleRow['Bild URL Variante ' . $i])) {
                    $this->addArticleToList($singleRow, $aArticles, 'Bild URL Variante ' . $i);
                }
            }
        }

//        function cmp($a, $b) : bool {
//            # sort by our sort number
//            return $a["order_nr"] > $b["order_nr"];
//        }
        usort($aArticles, function($a, $b) : bool {
            # sort by our sort number
            return $a["order_nr"] > $b["order_nr"];
        });

        return $aArticles;
    }

    /**
     * This function takes the template array and the list of articles and combines them.
     * Empty slots in the template are filled with a placeholder attribute.
     * Each slot is filled with text, image, prices and so on - every element becomes a separate entry
     * in the return array.
     *
     * @param array $aProductPlaces - the template to fill
     * @param array $aArticlesToSet - the list of articles
     * @return array - an array with the elements to include in the pdf (via pdfbox)
     */
    public function fillTemplateWithArticles(array $aProductPlaces, array $aArticlesToSet): array
    {

        $pageElements = [
            'title' => [],
            'image1' => [],
            'image2' => [],
            'image3' => [],
            'logo' => [],
            'price' => [],
//            'suggestedprice' => [],
            'colorcode' => [],
//            'redline' => [],
            'long_text' => [],
            'clickout' => []
        ];

        # since cyrillic is not supported by HelveticaNeue, we need to adjust
        $textFont = $this->countryCode == 'BG' ? 'ProximaNovaCondSemibold' : 'HelveticaNeueLTComRoman';

        $articleIndex = 0;
        $categories = [];
        foreach ($aProductPlaces as $page) {
            foreach ($page as $productSlot) {
                if ($productSlot != []) {
                    $categories[$this->removeSpecialChars($productSlot->category)] = $this->removeSpecialChars($productSlot->category);
                }
            }
        }
        foreach ($aArticlesToSet as $articleNr => $article) {
            if (!in_array($this->removeSpecialChars($article['category']), $categories)) {
                unset($aArticlesToSet[$articleNr]);
            }
        }
        $aArticlesToSet = array_values($aArticlesToSet);

        # fill each slot on a pdf page with an article or a placeholder
        foreach ($aProductPlaces as $page) {
            foreach ($page as $productSlot) {
                # match articles with product slots
                if ($productSlot != []
                    && isset($aArticlesToSet[$articleIndex])
                    && $this->removeSpecialChars($aArticlesToSet[$articleIndex]['category']) == $this->removeSpecialChars($productSlot->category)
                ) {
                    $productSlot->article = $aArticlesToSet[$articleIndex];
                    $this->_logger->info("Product page {$productSlot->page} -> slot {$productSlot->url} - Article {$articleIndex} -> {$aArticlesToSet[$articleIndex]['articleNumber']}");
                    $articleIndex++;
                } else {
                    continue;
                }

                # Y-offset for several lines in the long text
                $yOffset = 0.0;

                #title element
                list($pageElements, $yOffset) = $this->setTitleElement($productSlot, $yOffset, $textFont, $pageElements);
                # set product description / long text
                list($pageElements, $yOffset) = $this->setLongTextElement($productSlot, $yOffset, $textFont, $pageElements);
                # set the price element

                $pageElements = $this->setPriceElement($productSlot, $yOffset, $textFont, $pageElements);

                # if we have a suggested price, we set this price and the colored line
                if (isset($productSlot->article['suggested_retail_price']) && $productSlot->article['suggested_retail_price'] != 0) {
                    $pageElements = $this->setSuggestedPrice($productSlot, $yOffset, $textFont, $pageElements);
                    $pageElements = $this->setColoredLine($productSlot, $yOffset, $pageElements);
                }

                # set first image
                $pageElements = $this->setImage1($productSlot, $pageElements);
                # set second (overlap) image if it exists
                $pageElements = $this->setImage2($productSlot, $pageElements);
                # set color variants if they exist
                $pageElements = $this->setColorVariants($productSlot, $pageElements);
                # set the logos if they exist
                $pageElements = $this->setLogo($productSlot, $pageElements);
                # set the clickout links if they exist
                $pageElements = $this->setClickoutLinks($productSlot, $pageElements);


            }
        }

        for ($i = 0; $i < count($pageElements['image1']); $i++) {
            if ($pageElements['image1'][$i]['type'] == 'image' && $pageElements['image1'][$i]['path'] == NULL) {
                $pageElements['image1'][$i]['path'] = $this::tmpFilePath . 'Platzhalter.png';
            }
        }
        return $pageElements;
    }

    /**
     * This function takes an input string and the lenght for each row. It splits the
     * string into single words and combines them with padding to fit in rows with the
     *defined maximum length.
     *
     * @param string $textToLayout - the input string
     * @param int $charsPerRow - the maximum length of a single row
     * @return array - an array with the padded rows
     */
    public function buildTextLayout(string $textToLayout, int $charsPerRow): array
    {

        $charsPerRow = $this->countryCode == 'BG' ? $charsPerRow * 2 : $charsPerRow;

        # array with every input word as a separate element
        $aWords = preg_split("#(\s)#", $textToLayout);

        # this will be our return array
        $aRows = [];
        $index = 0;

        while ($index < count($aWords)) {

            $buffer = '';
            # security - if a single word is longer than the whole row, we give it back anyways
            if (strlen($aWords[$index]) > $charsPerRow) {
                $aRows[] = $aWords[$index];
                $index++;
                continue;
            }
            while (strlen($buffer) < $charsPerRow && $index < count($aWords)) {
                # if the next word is too long for our row, we break
                if (strlen($buffer) + strlen($aWords[$index]) > $charsPerRow) {
                    break;
                }
                $buffer = $buffer . ' ' . $aWords[$index];
                $index++;
            }

            $aRows[] = $buffer;
        }
        return $aRows;
    }

    public function getImagesFromField(?string $colorCodes, string $localImagePath): array
    {

        $colorVariantImages = [];
        $countryMap = [
            'DE' => 'D_AT',
            'AT' => 'D_AT',
            'NL' => 'NL',
            'IT' => 'IT',
            'SI' => 'SLO',
            'CZ' => 'CZ',
            'SK' => 'SK',
            'HU' => 'HU',
            'HR' => 'HR',
            'PL' => 'PL',
            'RO' => 'ROU',
            'BG' => 'BG'
        ];

        $colorCodes = preg_replace('#\n#', ',', trim($colorCodes));

        # we extract colorcodes like 34x0x255 and create a png for it
        if (preg_match_all('#(\d{1,3}x\d{1,3}x\d{1,3})#', $colorCodes, $matches)) {

            foreach ($matches[1] as $colorVariant) {

                $colorArray = explode("x", $colorVariant);
                $canvas = imagecreatetruecolor(10, 10);
                $color = imagecolorallocate($canvas, $colorArray[0], $colorArray[1], $colorArray[2]);
                imagefill($canvas, 0, 0, $color);
                imagepng($canvas, $localImagePath . $colorVariant . '.png');
                imagedestroy($canvas);
                $colorVariantImages[] = $localImagePath . $colorVariant . '.png';
                $this->_logger->info('creating ' . $localImagePath . $colorVariant . '.png');
            }

        } # if there are colorcodes as images, we extract them also
        elseif (preg_match('#\.(jpg|jpeg|png)$#', $colorCodes)) {
            foreach (explode(',', $colorCodes) as $singleImage) {
                # change Ökotex and package logos to the correct country version
                if (preg_match('#OTS-100#', $singleImage))
                    $singleImage = preg_replace('#_DE#', '_' . $this->countryCode, $singleImage);
                if (preg_match('#Pack_Einklinker#', $singleImage))
                    $singleImage = preg_replace('#_D_AT#', '_' . $countryMap[$this->countryCode], $singleImage);
                $colorVariantImages[] = $localImagePath . preg_replace('#\s#', '_', trim($singleImage));
            }
        }

        return $colorVariantImages;
    }

    /**
     * @param array $pageElements
     * @return string
     * @throws Exception
     */
    public function buildPdf(array $pageElements): string
    {
        $fileNameInserted = $this->brochureFile;
        foreach ($pageElements as $layerName => $elementLayer) {

            if ($layerName == 'clickout' || empty($elementLayer)) {
                continue;
            }

            $this->_logger->info('adding ' . $layerName . ' to pdf');

            # DEBUG: uncomment this for debugging purposes
            # you can set images one by one to find broken image files (causes NullPointerException)
            # Kik then has to re-upload the broken image
            if (in_array($layerName, ['temp'])) //['image1','image2','image3', 'logo']))
            {
                foreach ($elementLayer as $singleImage) {
                    # write the elements to JSON
                    $jsonFile = $this::tmpFilePath . $layerName . '.json';
                    $fh = fopen($jsonFile, 'w+');
                    fwrite($fh, json_encode([$singleImage]));
                    fclose($fh);

                    # add the JSON elements to the pdf template
                    $fileNameInserted = $this->sPdf->addElements(
                        $fileNameInserted,
                        $jsonFile);

                    if (rename($fileNameInserted, preg_replace('#_added_added.pdf#', '_added.pdf', $fileNameInserted)))
                        $fileNameInserted = preg_replace('#_added_added.pdf#', '_added.pdf', $fileNameInserted);

                    $this->_logger->info('created ' . $fileNameInserted);
                }

            } else {
                # write the elements to JSON
                $jsonFile = $this::tmpFilePath . $layerName . '.json';
                $fh = fopen($jsonFile, 'w+');
                fwrite($fh, json_encode($elementLayer));
                fclose($fh);

                # add the JSON elements to the pdf template
                $fileNameInserted = $this->sPdf->addElements(
                    $fileNameInserted,
                    $jsonFile);

                if (rename($fileNameInserted, preg_replace('#_added_added.pdf#', '_added.pdf', $fileNameInserted)))
                    $fileNameInserted = preg_replace('#_added_added.pdf#', '_added.pdf', $fileNameInserted);

                $this->_logger->info('created ' . $fileNameInserted);
            }
        }


        # mit Vorsicht zu genießen, da alle Annotation aus der Vorlage entfernt werden
        $this->sPdf->cleanAnnotations($fileNameInserted);

        $this->_logger->info('adding clickouts to pdf');
        $jsonFile = $this::tmpFilePath . 'clickout.json';

        $fh = fopen($jsonFile, 'w+');
        fwrite($fh, json_encode($pageElements['clickout']));
        fclose($fh);
        $fileNameInserted = $this->sPdf->setAnnotations($fileNameInserted, $jsonFile);

        rename($fileNameInserted, preg_replace('#.pdf#', '_' . $this->countryCode . '.pdf', $fileNameInserted));
        $fileNameInserted = preg_replace('#.pdf#', '_' . $this->countryCode . '.pdf', $fileNameInserted);

        $this->sFtp->connect("Kik", FALSE);
        $this->sFtp->upload($fileNameInserted, basename($fileNameInserted));
        return $fileNameInserted;
    }

    /**
     * @param $singleRow
     * @param array $aArticles
     * @param string $imageField
     */
    public function addArticleToList($singleRow, array &$aArticles, string $imageField): void
    {
        if (in_array($this->countryCode, ['DE', 'AT', 'CH'])) {
            $titleCol = 'Produktname';
            $longTextCol = 'Produktbeschreibung';

        } else {
            $titleCol = 'Übersetzung Produktname';
            $longTextCol = 'Übersetzung Produktbeschreibung';
        }
        if($this->countryCode == 'HU') {
            $decimals = 0;
        }
        else {
            $decimals = 2;
        }
        $singleRow['Preis aktuell'] = trim($singleRow['Preis aktuell']);
        $singleRow['Preis aktuell'] = str_replace(',', '.', $singleRow['Preis aktuell']);
        $singleRow['Preis aktuell'] = floatval($singleRow['Preis aktuell']);
        $singleRow['Preis aktuell'] = number_format($singleRow['Preis aktuell'], $decimals, ',', '');
        $aArticles[] = [
            # title will be split after 25 chars
            'title' => $this->buildTextLayout(trim($singleRow[$titleCol]), 24),
            'price' => $singleRow['Preis aktuell'] . $this::aCurrency[$this->countryCode],
            'suggested_retail_price' => trim($singleRow['Streichpreis (optional)']) != NULL ? trim($singleRow['Streichpreis (optional)']) . $this::aCurrency[$this->countryCode] : '',
            'validity' => '' . $singleRow['Gültigkeit'],
            'category' => $singleRow['Kategorie'],
            'order_nr' => $singleRow['Flyer Seite'] * 1000 + $singleRow['Layout Typ'] * 100 + $singleRow['Produktreihenfolge'] * 10,
            'image' => $this->getImagesFromField($singleRow[$imageField], $this::localPath),
            'colorcodes' => $this->getImagesFromField($singleRow['Farbcodes'], $this::localPath),
            'articleNumber' => $singleRow['Artikelnummer'],
            'clickout' => $singleRow['Klickout URL inkl. Tracking'],
            'long_text' => $this->buildTextLayout(trim($singleRow[$longTextCol]), 45),
            'logo' => $this->getImagesFromField($singleRow['Logo/Zusatz'], $this::localPath)
        ];

    }

    /**
     * this function compares the configured articles images (via Excel) with the images we got from the FTP server
     * missing images are printed as warning
     *
     * @param array $aArticlesToSet
     * @param array $aArticleImages
     */
    public function checkForMissingImages(array &$aArticlesToSet, array &$aArticleImages): void
    {
        foreach ($aArticlesToSet as $articleIndex => $singleArticle) {
            foreach ($singleArticle['image'] as $imageNumber => $singleImage) {
                if (!array_search($singleImage, $aArticleImages)) {
                    $this->_logger->warn(' Art. ' . $singleArticle['articleNumber'] . ' image ' . $imageNumber . ' does not exist: ' . $singleImage);
                    $aArticlesToSet[$articleIndex]['image'][$imageNumber] = $this::tmpFilePath . 'Platzhalter.png';
                }
            }
            foreach ($singleArticle['colorcodes'] as $imageNumber => $singleImage) {
                if (!array_search($singleImage, $aArticleImages)) {
                    $this->_logger->warn(' Art. ' . $singleArticle['articleNumber'] . ' colorcode does not exist: ' . $singleImage);
                    $aArticlesToSet[$articleIndex]['colorcodes'][$imageNumber] = $this::tmpFilePath . 'Platzhalter.png';
                }
            }
            foreach ($singleArticle['logo'] as $imageNumber => $singleImage) {
                if (!array_search($singleImage, $aArticleImages)) {
                    $this->_logger->warn(' Art. ' . $singleArticle['articleNumber'] . ' logo does not exist: ' . $singleImage);
                    $aArticlesToSet[$articleIndex]['logo'][$imageNumber] = $this::tmpFilePath . 'Platzhalter.png';
                }
            }

        }
    }

    /**
     * Helper function to remove special characters from a string
     * @param $dirtyString
     * @return string a string without special chars
     */
    private function removeSpecialChars($dirtyString): string
    {
        $dirtyString = str_replace(' ', '-', $dirtyString); // Replaces all spaces with hyphens.

        return preg_replace('/[^A-Za-z0-9\-]/', '', $dirtyString); // Removes special chars.
    }

    /**
     * @param stdClass $productSlot
     * @param float $yOffset
     * @param string $textFont
     * @param array $pageElements
     * @return array
     */
    private function setTitleElement(stdClass $productSlot,  float $yOffset, string $textFont, array $pageElements): array
    {
        # set title element
        foreach ($productSlot->article['title'] as $titleRow) {

            # OPTIONAL: to get the text centered, we need to calculate to x-offset - uncomment next line
            #$xOffset = (25.0 - strlen($titleRow))/2 * 6.0;
            $pageElements['title'][] = [
                'page' => $productSlot->page,
                'startX' => $productSlot->rectangle->startX + 23.0 ,
                'startY' => $productSlot->rectangle->startY + 60.0 + $yOffset,
                'type' => 'text',
                'contents' => $titleRow,
                'font' => ['fontType' => $textFont, 'fontSize' => 14.4, 'fontColor' => '0|0|0']
            ];

            #increase Offset for each line
            $yOffset -= 15.0;
        }
        return [$pageElements, $yOffset];
    }

    /**
     * @param stdClass $productSlot
     * @param float $yOffset
     * @param string $textFont
     * @param array $pageElements
     * @return array
     */
    private function setLongTextElement(stdClass $productSlot,  float $yOffset, string $textFont, array $pageElements): array
    {
        # set long text element
        foreach ($productSlot->article['long_text'] as $titleRow) {

            $pageElements['long_text'][] = [
                'page' => $productSlot->page,
                'startX' => $productSlot->rectangle->startX + 25.0 ,
                'startY' => $productSlot->rectangle->startY + 64.0 + $yOffset,
                'type' => 'text',
                'contents' => $titleRow,
                'font' => ['fontType' => $textFont, 'fontSize' => 7, 'fontColor' => '0|0|0']
            ];

            #increase Offset for each line
            $yOffset -= 10.0;
        }
        return [$pageElements, $yOffset];
    }

    /**
     * @param stdClass $productSlot
     * @param float $xOffset
     * @param float $yOffset
     * @param string $textFont
     * @param array $pageElements
     * @return array
     */
    private function setPriceElement(stdClass $productSlot, float $yOffset, string $textFont, array $pageElements): array
    {
        if (preg_match('#2x2#', $productSlot->url)) {
            # set silver price image
            preg_match_all('#(?<priceWithDecimals>(?<priceWithoutDecimals>[\d]+),(?<decimals>[\d]+))#', $productSlot->article['price'], $priceMatch);

            $price = $priceMatch['priceWithDecimals'][0];
            if ($priceMatch['decimals'][0] == '00') {
                $price = $priceMatch['priceWithoutDecimals'][0];
            }

            if (empty($price)) {
                $price = intval($productSlot->article['price']);
            }

            if (!in_array($this->countryCode, ['DE', 'AT'])) {
                $price = $price . '_' . $this->countryCode;
            }

            $silverPriceImage = 'nur_' . $price . '.png';
            Zend_Debug::dump($silverPriceImage);

            if (!file_exists($this::localPath . $silverPriceImage)) {
                $this->_logger->warn(' Price image does not exist: ' . $silverPriceImage);
                $pageElements['price'][] = $this->normalProductPrice($productSlot, $textFont);
                return $pageElements;
            }

            # set silver price image
            $pageElements['price'][] = [
                'page' => $productSlot->page,
                'startX' => $productSlot->rectangle->endX - 158.0,
                'endX' => $productSlot->rectangle->endX,
                'startY' => $productSlot->rectangle->startY,
                'endY' => $productSlot->rectangle->startY + 63.0 ,
                'type' => 'image',
                'path' => $this::localPath . $silverPriceImage,
                'scaling' => TRUE
            ];

            # set price tag image
            $pageElements['price'][] = [
                'page' => $productSlot->page,
                'startX' => $productSlot->rectangle->endX - 171.0,
                'endX' => $productSlot->rectangle->endX,
                'startY' => $productSlot->rectangle->startY + 47.0,
                'endY' => $productSlot->rectangle->startY + 157.0 ,
                'type' => 'image',
                'path' => $this::localPath . 'Der_Preis_spricht_fuer_sich_' . $this->countryCode . '.png',
                'scaling' => TRUE
            ];

        } else {
            # set price as text
            $pageElements['price'][] = $this->normalProductPrice($productSlot, $textFont);
        }
        return $pageElements;
    }

    /**
     * @param stdClass $productSlot
     * @param float $yOffset
     * @param string $textFont
     * @param array $pageElements
     * @return array
     */
    private function setSuggestedPrice(stdClass $productSlot, float $yOffset, string $textFont, array $pageElements): array
    {
        # set suggested price
        $pageElements['suggestedprice'][] = [
            'page' => $productSlot->page,
            'startX' => $productSlot->rectangle->startX + 30.0 ,
            'startY' => $productSlot->rectangle->startY + 51.0 + $yOffset,
            'type' => 'text',
            'contents' => $productSlot->article['suggested_retail_price'],
            'font' => ['fontType' => $textFont, 'fontSize' => 12.4, 'fontColor' => '0|0|0']
        ];
        return $pageElements;
    }

    /**
     * @param stdClass $productSlot
     * @param float $yOffset
     * @param array $pageElements
     * @return array
     */
    private function setColoredLine(stdClass $productSlot, float $yOffset, array $pageElements): array
    {
        # set colored line
        $pageElements['redline'][] = [
            'page' => $productSlot->page,
            'startX' => $productSlot->rectangle->startX + 28.0,
            'endX' => $productSlot->rectangle->startX + 73.0,
            'startY' => $productSlot->rectangle->startY + 47.0 + $yOffset,
            'endY' => $productSlot->rectangle->startY + 63.0 + $yOffset,
            'type' => 'line',
            'line' => ['lineWidth' => 1, 'lineColor' => '134|1|6']
        ];
        return $pageElements;
    }

    /**
     * @param stdClass $productSlot
     * @param array $pageElements
     * @return array
     */
    private function setImage1(stdClass $productSlot, array $pageElements): array
    {
        #reset Offset for the images
        $yOffset = 0.0;

        # if we have 2 images, we need an offset
        if (isset($productSlot->article['image'][1]) && $productSlot->article['image'][1] != NULL) {
            $yOffset = 10.0;
        }

        # set image
        $pageElements['image1'][] = [
            'page' => $productSlot->page,
            'startX' => $productSlot->rectangle->startX + 15,
            'endX' => $productSlot->rectangle->endX - 15.0,
            'startY' => $productSlot->rectangle->startY + 75.0 + $yOffset,
            'endY' => $productSlot->rectangle->endY - 5.0,
            'type' => 'image',
            'path' => $productSlot->article['image'][0],
            'scaling' => TRUE
        ];
        return $pageElements;
    }

    /**
     * @param stdClass $productSlot
     * @param array $pageElements
     * @return array
     */
    private function setImage2(stdClass $productSlot, array $pageElements): array
    {
        if (!isset($productSlot->article['image'][1]) || $productSlot->article['image'][1] == NULL)
            return $pageElements;

        #set image2
        $pageElements['image2'][] = [
            'page' => $productSlot->page,
            'startX' => $productSlot->rectangle->startX + 40.0,
            'endX' => $productSlot->rectangle->endX - 15.0,
            'startY' => $productSlot->rectangle->startY + 75.0,
            'endY' => $productSlot->rectangle->endY - 15.0,
            'type' => 'image',
            'path' => $productSlot->article['image'][1],
            'scaling' => TRUE
        ];
        return $pageElements;
    }

    /**
     * @param stdClass $productSlot
     * @param array $pageElements
     * @return array
     */
    private function setColorVariants(stdClass $productSlot, array $pageElements): array
    {
        # set color variants as squares
        $sizeFactor = preg_match('#2x2#', $productSlot->url) ? 2.0 : 1.0;
        $xOffset = 0.0;
        $yOffset = 0.0;
        $size = 15.0 * $sizeFactor;
        foreach ($productSlot->article['colorcodes'] as $colorVariant) {
            $pageElements['colorcode'][] = [
                'page' => $productSlot->page,
                'startX' => $productSlot->rectangle->startX + 2.0 + $xOffset,
                'endX' => $productSlot->rectangle->startX + 2.0 + $size + $xOffset,
                'startY' => $productSlot->rectangle->startY + $yOffset,
                'endY' => $productSlot->rectangle->startY + $size + $yOffset,
                'type' => 'image',
                'path' => $colorVariant,
                'scaling' => FALSE
            ];
            $xOffset += (2.0 + $size);
        }
        return $pageElements;
    }

    /**
     * @param stdClass $productSlot
     * @param array $pageElements
     * @return array
     */
    private function setLogo(stdClass $productSlot, array $pageElements): array
    {
        # set logo, if it exists
        $xOffset = 0;
        $sizeFactor = preg_match('#2x2#', $productSlot->url) ? 2.0 : 1.0;
        foreach ($productSlot->article['logo'] as $logo) {
            list($width, $height) = getimagesize($logo);
            $factor = $width / $height;

            #$this->_logger->info($logo . " : " . $width . "x" . $height);
            if ($factor > 1) {
                $height = 50.0;
                $width = $height * $factor;
            } else {
                $width = 50.0;
                $height = $width / $factor;
            }
            # if the width is bigger than half of the product, scale it down
            if ($width >= 75 || preg_match('#Mouse_EXP.png#', $logo)) {
                $width = 0.5 * $width;
                $height = 0.5 * $height;
            }
            if (preg_match('#Pack_Einklinker.*.png#', $logo)) {
                $width = 38.0;
                $height = 38.0;
                $pageElements['logo'][] = [
                    'page' => $productSlot->page,
                    'startX' => $productSlot->rectangle->endX - $width - 1.0,
                    'endX' => $productSlot->rectangle->endX - 1.0,
                    'startY' => $productSlot->rectangle->endY - $height,
                    'endY' => $productSlot->rectangle->endY,
                    'type' => 'image',
                    'path' => $logo,
                    'scaling' => TRUE
                ];
                continue;
            }
            if (preg_match('#OTS-100.*.png#', $logo)) {
                $height = 40.0 * $sizeFactor;
                $width = $height * $factor;
                # set image
                $pageElements['logo'][] = [
                    'page' => $productSlot->page,
                    'startX' => $productSlot->rectangle->endX - $width - 4.0,
                    'endX' => $productSlot->rectangle->endX - 4.0,
                    'startY' => $productSlot->rectangle->startY + 75.0,
                    'endY' => $productSlot->rectangle->startY + 75.0 + $height,
                    'type' => 'image',
                    'path' => $logo,
                    'scaling' => TRUE
                ];
                continue;
            }

            $height = $height * $sizeFactor;
            $width = $width * $sizeFactor;

            # set image
            $pageElements['logo'][] = [
                'page' => $productSlot->page,
                'startX' => $productSlot->rectangle->startX + $xOffset,
                'endX' => $productSlot->rectangle->startX + $width + $xOffset,
                'startY' => $productSlot->rectangle->endY - $height,
                'endY' => $productSlot->rectangle->endY,
                'type' => 'image',
                'path' => $logo,
                'scaling' => TRUE
            ];
            $xOffset += ($width + 2.0);
        }
        return $pageElements;
    }

    /**
     * @param stdClass $productSlot
     * @param array $pageElements
     * @return array
     */
    private function setClickoutLinks(stdClass $productSlot, array $pageElements): array
    {
        # set clickout links
        $pageElements['clickout'][] = [
            'page' => $productSlot->page,
            'startX' => $productSlot->rectangle->startX,
            'startY' => $productSlot->rectangle->startY,
            'endX' => $productSlot->rectangle->endX,
            'endY' => $productSlot->rectangle->endY,
            'link' => $productSlot->article['clickout']
        ];
        return $pageElements;
    }

    private function createMissingImagePlaceholder(): void
    {
        # create the placeholder image (if an image is missing, this is used instead)
        $canvas = imagecreatetruecolor(10, 10);
        $color = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $color);
        imagepng($canvas, APPLICATION_PATH . '/../public/files/tmp/Platzhalter.png');
        imagedestroy($canvas);
    }

    private function normalProductPrice(stdClass $productSlot, string $textFont): array
    {
        return [
            'page' => $productSlot->page,
            'startX' => $productSlot->rectangle->startX + 80.0 ,
            'startY' => $productSlot->rectangle->startY + 10.0, # + $yOffset,
            'type' => 'text',
            'contents' => $productSlot->article['price'],
            'font' => ['fontType' => $textFont, 'fontSize' => 21.6, 'fontColor' => '0|0|0']
        ];
    }
}
