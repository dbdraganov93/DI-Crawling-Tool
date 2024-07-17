<?php

/**
 * Article crawler for BabyOne (ID: 28698)
 */

class Crawler_Company_BabyOne_DiscoverArticle extends Crawler_Generic_Company
{
    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private Marktjagd_Service_Input_PhpSpreadsheet $phpSheetService;
    private Marktjagd_Service_Input_Page $inputPageService;
    private Marktjagd_Service_Input_GoogleSpreadsheetRead $googleSpreadSheed;
    private const GOOGLE_SHEET_ID = '1gtNocU-e2-i1uBNu0CMuyTnbXbyydMoqLPszFNfc_R0';
    private const DATE_PATTERN = '#(\d){2}\.(\d){2}\.(\d){4}#';
    private const ARTICLE_PATTERN = '#Artikellist#';
    private const DYNAMIC_FLYER = 'dynamic_flyer_and_discover';

    public function __construct()
    {
        parent::__construct();

        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->phpSheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $this->inputPageService = new Marktjagd_Service_Input_Page();
        $this->googleSpreadSheed = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
    }

    /**
    *  @throws Exception
    */
    public function crawl($companyId)
    {
        # look for an articles file on the FTP server
        $localPath = $this->ftp->connect($companyId, TRUE);
        $this->ftp->changedir(self::DYNAMIC_FLYER);
        $articles = new Marktjagd_Collection_Api_Article();

        $localArticleFile = $this->downloadFile($localPath);

        # look for a dynamic brochure in the marketing plan (no dyn. brochure, nothing to do)
        $brochurePlan = $this->brochurePlan();

        if (!$localArticleFile || !$brochurePlan['endDate']) {
            $this->_logger->info('no articles need to be imported');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            return $this->_response;
        }
        $articlesFeeds = $this->phpSheetService->readFile($localArticleFile)->getElement(0)->getData();

        foreach ($articlesFeeds as $articleFeed) {

            try {
                $articleData = $this->getArticleData($articleFeed, $brochurePlan['utmParameter'], $brochurePlan['startDate'], $brochurePlan['endDate']);
                $article = $this->createArticle($articleData);
                $articles->addElement($article, true, 'complex', false);
            } catch (Exception $e) {
                $this->_logger->warn($e->getMessage());
            }
        }
        return $this->getResponse($articles, $companyId);
    }

    private function brochurePlan(): array
    {
        $brochurePlan = $this->googleSpreadSheed->getFormattedInfos(self::GOOGLE_SHEET_ID, 'A1', 'I', 'geplant');

        foreach ($brochurePlan as $brochureData) {

            if (!preg_match(self::DATE_PATTERN, $brochureData['Startdatum'])) {
                $this->_logger->warn('Something is wrong with the Start Date Regex: ' . $brochureData['Startdatum']);
            }
            if (!preg_match(self::DATE_PATTERN, $brochureData['Enddatum'])) {
                $this->_logger->warn('Something is wrong with the End Date Regex: ' . $brochureData['Enddatum']);
            }
            if (!empty($brochureData['PDF Datei']) && $brochureData['DE'] == 'ja' && strtotime($brochureData['Enddatum']) >= time()) {
                $startDate = $brochureData['Startdatum'];
                $endDate = $brochureData['Enddatum'];
                $utmParameter = preg_replace('#^[^\?]+#','', $brochureData['Link DE']);
                break;
            }
        }
        return [
            'startDate' => $startDate,
            'endDate'  => $endDate,
            'utmParameter' => $utmParameter
        ];
    }

    /**
     * @throws Exception
     */
    private function getArticleData(array $articleData, string $utmParameter, string $startDate, string $endDate): array
    {
        # build the URL for each article
        $i = 0;
        $productsSkipped = [];

        foreach ($articleData as $singleArticle) {

            if (empty($singleArticle[1])) {
                continue;
            }
            $i += 1;
            if ($i % 100 == 0) {
                $this->_logger->info($i);
            }
            [$singleArticle['Kategorie'], , ,$singleArticle['Artikelnummer']] = explode(';', $singleArticle[1]);

            // This is the way to use the own website redirect to get the actual product website
            $singleArticle['URL'] = 'https://www.babyone.de/perma/detail/' . $singleArticle['Artikelnummer'];
            $this->_logger->info($i);

            if (!preg_match('#^http#', $singleArticle['URL']) || empty($singleArticle['Artikelnummer']) ) {
                throw new Exception('This URL was skipped: ' . $singleArticle['URL']);
            }
            $this->_logger->info('Getting data from URL: ' . $singleArticle['URL']);
            $this->inputPageService->open($singleArticle['URL']);
            $page = $this->inputPageService->getPage()->getResponseBody();
            $xpath = new DOMXPath($this->createDomDocument($page));

            $title = trim($xpath->query('//h1[@class="product-detail-name"]')->item(0)->textContent);

            if (empty($title)) {
                $productsSkipped[] = $singleArticle['Artikelnummer'];
                throw new Exception('This URL was skipped, no Title Found: ' . $singleArticle['URL']);
            }
            // description and manufacturer
            $description = trim($xpath->query('//div[@class="product-detail-description-text"]')->item(0)->textContent);
            $manufacturer = trim($xpath->query('//span[@class="manufacturer-name"]')->item(0)->textContent);

            $imageNode = $xpath->query('//div[@class="gallery-slider-item is-contain js-magnifier-container"]/img')->item(0); //srcset
            $imageUrl = $imageNode->getAttribute('data-full-image');

            if (empty($imageUrl)) {
                $productsSkipped[] = $singleArticle['Artikelnummer'];
                throw new Exception('This URL was skipped, no Image Found: ' . $singleArticle['URL']);
            }
            $price = str_replace(' €*', '', trim(
                $xpath->query('//p[@class="product-detail-price with-list-price"]')->item(0)->textContent
            ));
            if (empty($price)) {
                $price = str_replace(' €*', '', trim(
                    $xpath->query('//p[@class="product-detail-price"]')->item(0)->textContent
                ));
            }
            if (empty($price)) {
                $productsSkipped[] = $singleArticle['Artikelnummer'];
                throw new Exception('This URL was skipped, no Price Found: ' . $singleArticle['URL']);
            }
            $originalPrice = str_replace(' €', '', str_replace('UVP ', '', trim(
                $xpath->query('//span[@class="list-price-price"]')->item(0)->textContent
            )));

            if (!empty($productsSkipped)) {
                $this->_logger->warn('--> Not all products were imported!! ');
                $this->_logger->warn(implode(', ', $productsSkipped));
            }
            $articleData = [
                'title' => $title,
                'url' => $singleArticle['URL'],
                'start' => $startDate,
                'end' => $endDate,
                'description' => $description,
                'manufacturer' => $manufacturer,
                'imageUrl' => $imageUrl,
                'price' => $price,
                'originalPrice' => $originalPrice,
                'articleNumber' => $singleArticle['Artikelnummer'],
                'utmParameter' => $utmParameter,
            ];
        }
        return $articleData;
    }

    private function createArticle(array $data): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();

        $article->setTitle($data['title'])
            ->setUrl($data['url']. $data['utmParameter'])
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart($article->getStart())
            ->setArticleNumber($data['articleNumber'])
            ->setText($data['description'])
            ->setPrice($data['price'])
            ->setSuggestedRetailPrice($data['originalPrice'])
            ->setManufacturer($data['manufacturer'])
            ->setImage($data['imageUrl']);

        return $article;
    }

    private function downloadFile($localPath): string
    {
        foreach ($this->ftp->listFiles() as $singleFtpFile) {
            if (preg_match(self::ARTICLE_PATTERN, $singleFtpFile)) {
                $localArticleFile = $this->ftp->downloadFtpToDir($singleFtpFile, $localPath);
                $this->ftp->close();
                break;
            }
        }
        return $localArticleFile;
    }

    private function createDomDocument(string $url): DOMDocument
    {
        // ignore warnings
        $old_libxml_error = libxml_use_internal_errors(true);
        $domDoc = new DOMDocument();
        $domDoc->loadHTML($url);
        libxml_use_internal_errors($old_libxml_error);

        return $domDoc;
    }
}
