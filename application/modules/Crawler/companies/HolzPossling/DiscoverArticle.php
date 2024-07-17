<?php

use Crawler_Company_HolzPossling_DiscoverHelpers as DiscoverHelpers;

/**
 * Article crawler for Holz Possling (ID: 71464)
 */
class Crawler_Company_HolzPossling_DiscoverArticle extends Crawler_Generic_Company
{
    protected int $companyId;
    protected array $campaignData = [];
    private array $articleFeed;

    public function __construct()
    {
        parent::__construct();

        $googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->campaignData = $googleSpreadsheet->getCustomerData(DiscoverHelpers::CUSTOMER_DATA_TAB);
        $this->articleFeed = $googleSpreadsheet->getFormattedInfos($this->campaignData['articleFile'], 'A1', 'I', $this->campaignData['tabNameArticles']);
    }

    public function crawl($companyId)
    {
        ini_set('memory_limit', '8G');
        $this->companyId = $companyId;
//
//        $discoverHelpers = new DiscoverHelpers();
//        $articlesFeed = $discoverHelpers->getArticlesFeed($this->campaignData['articleFile']);

        $articles = new Marktjagd_Collection_Api_Article();
        foreach ($this->articleFeed as $articleFeedData) {
            if (empty($articleFeedData['ArtNr'])) {
                continue;
            }

            $this->_logger->info('getting article ' . $articleFeedData['ArtNr']);
            $articleData = $this->getArticleData($articleFeedData);
            $article = $this->createArticle($articleData);

            $articles->addElement($article, TRUE, 'complex', FALSE);
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getArticleData(array $articleFeedData): array
    {
        $articleInfo = $this->getArticleInfo($articleFeedData['ArtNr']);

        $articleTitle = $this->getArticleTitle($articleFeedData['Produkt']);

        $additionalProperties = [];
        if (is_float($articleFeedData['Grundpreis'])) {
            $additionalProperties = [
                "unitPrice" => [
                    "value" => round((float)trim(preg_replace(['#([^/]+)\s*/[^/]+#', '#\,#', '#[^\d\.]#'], ['$1', '.', ''], $articleFeedData['Grundpreis'])), 2),
                    "unit" => preg_replace('#\.#', '', $articleFeedData["Einheit"])
                ]
            ];
        }

        return [
            'title' => $articleTitle,
            'text' => $articleFeedData['Kurztext'],
            'articleNumber' => $this->campaignData['brochureNumber'] . '_' . $articleFeedData['ArtNr'],
            'price' => preg_replace(['#^\D*#', '#\s*€$#'], '', trim($articleFeedData['Werbepreis'])),
            'image' => 'https://www.possling.de' . $articleInfo->haupt_artikelbild,
            'url' => $articleFeedData["Produkt"],
            'start' => $this->campaignData['validStart'],
            'end' => $this->campaignData['validEnd'],
            'visibleStart' => $this->campaignData['validStart'],
            'additionalProperties' => json_encode($additionalProperties)
        ];
    }

    private function getArticleInfo(string $articleNumber): object
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://www.possling.de/scripts/preisliste/artikeldetails_neu.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'artnr=' . $articleNumber,
            CURLOPT_HTTPHEADER => [
                'Origin: https://www.possling.de'
            ],
        ));

        $response = json_decode(curl_exec($curl));
        curl_close($curl);

        return $response;
    }

    private function getArticleTitle(string $articleUrl): ?string
    {
        $pageService = new Marktjagd_Service_Input_Page();

        $pattern = '#(katalog/(\d+)/|searchterm=(\d+))#';
        if (!preg_match($pattern, $articleUrl, $articleNumberMatch)) {
            $this->_logger->err($this->companyId . ': ' . $articleUrl);

            return null;
        }
        $pageUrl = 'https://www.possling.de/preisliste/Suchanfrage/katalog/' . $articleNumberMatch[count($articleNumberMatch) - 1] . '/galerie/artikel.php';
        $this->_logger->info($this->companyId . ': opening ' . $pageUrl);

        $pageService->open($pageUrl);
        $page = $pageService->getPage()->getResponseBody();

        $pattern = '#<title[^>]*>[^-]+-\s*([^<]+?)</title>#';
        if (!preg_match($pattern, $page, $titleMatch)) {
            $this->_logger->err($this->companyId . ': ' . $articleUrl);

            return null;
        }

        return $titleMatch[1];
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();

        return $article->setTitle($articleData['title'])
            ->setText($articleData['text'])
            ->setArticleNumber($articleData['articleNumber'])
            ->setPrice($articleData['price'])
            ->setImage($articleData['image'])
            ->setUrl($articleData['url'])
            ->setStart($articleData['start'])
            ->setEnd($articleData['end'])
            ->setVisibleStart($articleData['visibleStart'])
            ->setAdditionalProperties($articleData['additionalProperties']);
    }
}
