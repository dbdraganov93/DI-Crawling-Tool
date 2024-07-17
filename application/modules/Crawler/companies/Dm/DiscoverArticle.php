<?php

/*
 * Artikel Crawler für DM (ID: 27)
 */

class Crawler_Company_Dm_DiscoverArticle extends Crawler_Generic_Company
{
    public const DM_DISCOVER_PRODUCT_SUFFIX = '_Disc';
    private const PRODUCTS_PER_CATEGORY = 26;
    private array $articleData;
    private string $numberPrefix;
    private Marktjagd_Service_Input_PhpSpreadsheet $excelService;

    public function __construct()
    {
        parent::__construct();
        $googleReader = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->articleData = $googleReader->getCustomerData('dmGer');
        $this->excelService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $this->numberPrefix = self::getNumberPrefix($this->articleData['brochure_number'], $this->articleData['start_date']);
    }

    public function crawl($companyId)
    {
        ini_set('memory_limit', '6G');
        $articles = new Marktjagd_Collection_Api_Article();

        $specialArticles = $this->specialCategoryArticles($companyId);
        $categories = array_flip(explode(', ', $this->articleData['categories']));
        foreach ($categories as $key => $int) {
            $categories[$key] = [];
        }
        $articlesData = $this->getArticlesData($companyId);
        foreach ($articlesData as $articleData) {
            if (in_array($articleData['arcticle_number'], $specialArticles)
                || (array_key_exists($articleData['category_path_1'], $categories)
                    && count($categories[$articleData['category_path_1']]) < self::PRODUCTS_PER_CATEGORY)) {
                if (!empty($articleData['grundpreis'])) {
                    $additionalProperties = [
                        "unitPrice" => [
                            "value" => (float)trim(preg_replace(['#([^\/]+)\s*\/[^\/]+#', '#\,#', '#[^\d\.]#'], ['$1', '.', ''], $articleData['grundpreis'])),
                            "unit" => preg_replace('#\.\d+#', '', trim($articleData["unit_pricing_base_measure"]))
                        ]
                    ];
                    $articleData['additional_properties'] = json_encode($additionalProperties);
                }
                $discoverArticle = $this->createArticle($articleData, self::DM_DISCOVER_PRODUCT_SUFFIX);
                $articles->addElement($discoverArticle, true, 'complex', false);
                unset($articleData['additional_properties']);
                $categories = $this->getCategories($articleData, $discoverArticle->getArticleNumber(), $specialArticles, $categories);
            }

            if (preg_match('#free#', $articleData['price'])) {
                continue;
            }

            if (preg_match('#(Vibra|Penis|Menstruation|MOQQA|Kondom|Sperm)#', $articleData['title'])) {
                continue;
            }

            if (preg_match('#(Desinfektionsmittel|Toilettenpapier)#', $articleData['title'])) {
                continue;
            }

            if (strtotime('now') > strtotime('24.12.' . date('Y'))
                || strtotime('now') < strtotime('01.09.' . date('Y'))) {
                if (preg_match('#(Weihnacht|Advents|Nikolaus|Lebkuch|Spekulatius|Dominosteine|Christ\w{3})#', $articleData['title'])) {
                    continue;
                }
            }

            $article = $this->createArticle($articleData);
            $articles->addElement($article, true, 'complex', false);
        }

        $this->uploadCategoryFile($categories);

        return $this->getResponse($articles, $companyId);
    }

    public static function getNumberPrefix(string $brochureNumber, string $startDate): string
    {
        if (!preg_match('#kw(\d{2})#i', $brochureNumber, $kwMatch)) {
            throw new Exception('DM Discover: Can\'t match the kw from the brochure number!');
        }

        $kw = $kwMatch[1] ?? date('W', strtotime($startDate));
        $year = date('Y', strtotime($startDate));

        return 'KW' . $kw . '_' . $year . '_';
    }

    private function getArticlesData(int $companyId): array
    {
        $httpService = new Marktjagd_Service_Transfer_Http();
        $localPath = $httpService->generateLocalDownloadFolder($companyId);
        $localArticleFile = $httpService->getRemoteFile($this->articleData['feed_url'], $localPath);
        return $this->excelService->readFile($localArticleFile, TRUE, ';')->getElement(0)->getData();
    }

    private function specialCategoryArticles(int $companyId): array
    {
        $excel = new Marktjagd_Service_Input_PhpExcel();
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $ftp->connect($companyId, TRUE);

        $aSpecialCampaign = [];
        $localSpecialCampaignFile = $ftp->downloadFtpToDir('./Discover/' . $this->articleData['special_category_file'], $localPath);
        $ftp->close();

        $articlesData = $excel->readFile($localSpecialCampaignFile)->getElement(0)->getData();
        $aHeader = [];
        foreach ($articlesData as $articleData) {
            if (!strlen($articleData[2])) {
                continue;
            }

            if (!$aHeader) {
                $aHeader = $articleData;
                continue;
            }
            $aInfo = array_combine($aHeader, $articleData);
            $aSpecialCampaign[] = $aInfo['GTIN'];
        }

        return $aSpecialCampaign;
    }

    private function createArticle(array $articleData, string $articleNumberSuffix = ''): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        $article->setPrice($articleData['price'])
            ->setTitle($articleData['title'])
            ->setArticleNumber($this->numberPrefix . $articleData['arcticle_number'] . $articleNumberSuffix)
            ->setText($articleData['text'] . '<br/><br/>' . $articleData['tags'])
            ->setEan($articleData['ean'])
            ->setSuggestedRetailPrice($articleData['suggested_retail_price'])
            ->setStart($articleData['start'] ?: $this->articleData['start_date'])
            ->setEnd(($articleData['end'] ?: $this->articleData['end_date']))
            ->setVisibleStart($articleData['visible_start'] ?: $this->articleData['start_date'])
            ->setVisibleEnd(($articleData['visible_end'] ?: $this->articleData['end_date']))
            ->setImage($articleData['image'])
            ->setManufacturer($articleData['manufacturer'])
            ->setArticleNumberManufacturer($articleData['article_number_manufacturer'])
            ->setTrademark($articleData['trademark'])
            ->setColor($articleData['color'])
            ->setSize($articleData['size'])
            ->setAmount($articleData['amount'])
            ->setShipping($articleData['shipping'])
            ->setUrl($articleData['deeplink']);

        if ($articleData['additional_properties']) {
            $article->setAdditionalProperties($articleData['additional_properties']);
        }

        return $article;
    }

    /**
     * @param $articleData
     * @param array $specialArticles
     * @param array $categories
     * @return array
     */
    public function getCategories($articleData, string $articleNumber, array $specialArticles, array $categories): array
    {
        if (in_array($articleData['arcticle_number'], $specialArticles)) {
            $categories[array_key_first($categories)][] = $articleNumber;
        } else {
            $categories[$articleData['category_path_1']][] = $articleNumber;
        }
        return $categories;
    }

    private function uploadCategoryFile(array $categories)
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $fh = fopen(APPLICATION_PATH . '/../public/files/tmp/dm_disc.csv', 'w+');
        fputcsv($fh, ['category', 'articleNumber'], ';');
        foreach ($categories as $category => $aArticleNumber) {
            foreach ($aArticleNumber as $singleArticleNumber) {
                fputcsv($fh, [$category, $singleArticleNumber], ';');
            }
        }
        fclose($fh);

        $ftp->connect('27');
        $ftp->upload(APPLICATION_PATH . '/../public/files/tmp/dm_disc.csv', './Discover/disc_' . $this->numberPrefix . '.csv');
        $ftp->close();
    }
}
