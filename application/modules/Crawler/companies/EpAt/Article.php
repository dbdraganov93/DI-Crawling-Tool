<?php

/**
 * Article Crawler für EP AT (ID: 72750)
 */

class Crawler_Company_EpAt_Article extends Crawler_Generic_Company
{
    private const DATE_FORMAT = 'd.m.Y';

    private bool $centralEpStoreFound = false;
    private int $companyId;

    private Crawler_Company_EpAt_DiscoverHelpers $helperMethods;

    public function __construct()
    {
        parent::__construct();

        $this->helperMethods = new Crawler_Company_EpAt_DiscoverHelpers();
    }

    public function crawl($companyId): Crawler_Generic_Response
    {
        ini_set('memory_limit', '2G');

        $this->companyId = $companyId;
        $articles = new Marktjagd_Collection_Api_Article();

        $zipFiles = $this->helperMethods->downloadZipsFromClientFTP($companyId);
        foreach ($zipFiles as $zip) {
            $articleFiles = $this->extractArticleFiles($zip);

            foreach ($articleFiles as $storeNumber => $filePath) {
                $this->centralEpStoreFound = $this->centralEpStoreFound || $this->helperMethods->isCentralEPStore($storeNumber);

                $articlesToImport = $this->getArticlesToImport($filePath, $storeNumber, $zip);
                # we repeat the import from the last csv file but modify it to work for the central EP shop
                if (!$this->centralEpStoreFound) {
                    $centralStoreArticles = $this->getArticlesToImport(end($articleFiles), $this->helperMethods->getCentralEPStore(), $zip);
                    $articlesToImport = array_merge($articlesToImport, $centralStoreArticles);
                }

                foreach ($articlesToImport as $articleData) {
                    $article = $this->createArticle($articleData);
                    $articles->addElement($article, FALSE, 'complex', FALSE);
                }
            }
        }

        return $this->getResponse($articles, $companyId);
    }

    private function extractArticleFiles(string $zipFile): array
    {
        $extractPath = $this->helperMethods->unzipFile($zipFile, $this->companyId);

        $directory = new RecursiveDirectoryIterator($extractPath, FilesystemIterator::SKIP_DOTS);
        $recursiveIterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);

        $articleFiles = [];
        foreach ($recursiveIterator as $file) {
            if (0 !== strpos($file->getBasename(), '.')) {
                if (preg_match('#\.csv$#', $file->getBasename())) {
                    $articleFiles[$file->getBasename('.csv')] = $file->getPathname();
                }
            }
        }

        return $articleFiles;
    }

    private function getArticlesToImport(string $articleFile, string $storeNumber, string $zip = ''): array
    {
        $phpSpreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $articlesData = $phpSpreadsheetService->readFile($articleFile, TRUE, ';')->getElement(0)->getData();

        $articlesToImport = [];
        foreach ($articlesData as $articleData) {
            if (strtotime($articleData['end']) < time()) {
                continue;
            }

            $articlesToImport[] = $this->prepareArticleData($articleData, $storeNumber, $zip);
        }

        return $articlesToImport;
    }

    private function prepareArticleData(array $articleData, string $storeNumber, string $zip): array
    {
        $strAdditionalAttributes = '';
        if (strlen($articleData['EnergyLabel']) && strlen($articleData['EnergyLabelScale'])) {
            $strAdditionalAttributes = json_encode(['energyLabel' => $articleData['EnergyLabel'], 'energyLabelType' => $articleData['EnergyLabelScale']]);
        }

        $national = 0;
        if ($this->helperMethods->isCentralEpStore($storeNumber)) {
            $articleData['Clickout URL'] = preg_replace('#www\.ep\.at\/([^\/]*)\/p\/#', 'https://www.ep.at/p/', $articleData['Clickout URL']);
            $national = 1;
        }

        $url = $articleData['Clickout URL'];
        if(!preg_match('#^https://#', $url)) {
            $url = 'https://' . $url;
        }

        return [
            'title' => preg_replace('#Ž#', '', $articleData['Name']),
            'articleNumber' => basename($zip, '.zip') . '_' . $storeNumber . '_' . $articleData['ID'],
            'text' => $articleData['Description'],
            'price' => $articleData['Final Price'],
            'suggestedRetailPrice' => $articleData['List Price'],
            'url' => $url,
            'image' => $articleData['Image Link'],
            'start' => date(self::DATE_FORMAT, strtotime($articleData['start'])),
            'end' => date(self::DATE_FORMAT, strtotime($articleData['end'])),
            'visibleStart' => date(self::DATE_FORMAT, strtotime($articleData['start'])),
            'storeNumber' => $storeNumber,
            'national' => $national,
            'additionalProperties' => $strAdditionalAttributes
        ];
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();

        return $article->setTitle($articleData['title'])
            ->setArticleNumber($articleData['articleNumber'])
            ->setText($articleData['text'])
            ->setPrice($articleData['price'])
            ->setSuggestedRetailPrice($articleData['suggestedRetailPrice'])
            ->setUrl($articleData['url'])
            ->setImage($articleData['image'])
            ->setStart($articleData['start'])
            ->setEnd($articleData['end'])
            ->setVisibleStart($articleData['visibleStart'])
            ->setStoreNumber($articleData['storeNumber'])
            ->setAdditionalProperties($articleData['additionalProperties']);
    }
}