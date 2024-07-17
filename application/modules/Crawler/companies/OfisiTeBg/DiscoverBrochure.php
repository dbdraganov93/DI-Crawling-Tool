<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

use \Crawler_Company_OfisiTeBg_DiscoverArticle as OfisiTeBgDiscoverArticle;
/**
 * Discover brochure crawler for OfisiTe BG (ID: 80617)
 */
class Crawler_Company_OfisiTeBg_DiscoverBrochure extends Crawler_Generic_Company
{
    private const COVER_PAGE_FOLDER_ID = '1WYS89BRsfsMJzfg-LSg01gPBuVWFvIQ8';
    private const BROCHURE_NUMBER_FORMAT = 'DC_%s_OfisiteBg';

    private int $companyId;

    private Marktjagd_Service_Input_UrlReader $pageContent;
    private Marktjagd_Service_Input_HtmlParser $htmlParser;
    private array $campaign;
    private string $brochureNumber;


    public function __construct()
    {
        parent::__construct();

        $this->pageContent = new Marktjagd_Service_Input_UrlReader();
        $this->htmlParser = new Marktjagd_Service_Input_HtmlParser();

        $this->campaign = OfisiTeBgDiscoverArticle::getCampaignData();

        $this->brochureNumber = sprintf(self::BROCHURE_NUMBER_FORMAT, str_replace('.', '', $this->campaign['start']));
    }

    function crawl($companyId)
    {
        $this->companyId = $companyId;
        $brochures = new Marktjagd_Collection_Api_Brochure();
        $pageContent = $this->pageContent->getContent($this->campaign['source_url']);
        $productsPerCategory = $this->getProductsPerCategory($this->htmlParser->parseHtml($pageContent));

        $layout = $this->generateLayout($productsPerCategory);

        $brochureUrl = $this->getBrochureUrl();
        if (!$brochureUrl) {
            throw new Exception('Company ID: ' . $companyId . ': can\'t find the cover page!');
        }

        $brochure = $this->createBrochure($layout, $brochureUrl);
        $brochures->addElement($brochure);

        return $this->getResponse($brochures, $companyId);
    }

    private function getProductsPerCategory(DOMDocument $page): array
    {
        $productsPerCategory = [];
        foreach ($page->getElementsByTagName('div') as $div) {
            if (preg_match('#_grid-section#', $div->getAttribute('class'))) {
                $category = $div->getElementsByTagName('h2')[0]->nodeValue;

                foreach ($div->getElementsByTagName('a') as $link) {
                    $url = $link->getAttribute('href');
                    if (!empty($url) && preg_match('#^https?://#', $url) && (!isset($productsPerCategory[$category]) || !in_array($url, $productsPerCategory[$category]))) {
                        $productsPerCategory[$category][] = $url;
                    }
                }
            }
        }

        return $productsPerCategory;
    }

    private function generateLayout(array $productsPerCategory): string
    {
        $api = new Marktjagd_Service_Input_MarktjagdApi();

        $articleIds = [];
        $articles = $api->getActiveArticleCollection($this->companyId);
        foreach ($articles->getElements() as $article) {
            $articleIds[$article->getArticleNumber()] = $article->getArticleId();
        }

        $pages = [];
        foreach ($productsPerCategory as $category => $productUrls) {
            foreach ($productUrls as $productUrl) {
                $articleNumber = OfisiTeBgDiscoverArticle::getArticleNumber($productUrl, $this->campaign['start']);
                if (empty($articleNumber) || !isset($articleIds[$articleNumber])) {
                    continue;
                }

                $pages[$category]['products'][] = [
                    'priority' => rand(1, 3),
                    'product_id' => $articleIds[$articleNumber]
                ];
                $pages[$category]['page_metaphor'] = $category;
            }
        }

        if (empty($pages)) {
            throw new Exception('Company ID: ' . $this->companyId . ': No products found!');
        }

        $pages = array_values($pages);

        $response = Blender::blendApi($this->companyId, $pages, $this->brochureNumber);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        return $response['body'];
    }

    private function getBrochureUrl(): string
    {
        $googleDrive = new Marktjagd_Service_Google_Drive();
        $pdfFiles = $googleDrive->getFiles(self::COVER_PAGE_FOLDER_ID, 'pdf');
        foreach ($pdfFiles as $pdf) {
            if ($this->campaign['cover_page'] === $pdf['name']) {
                return $googleDrive->downloadFile($pdf['id'], APPLICATION_PATH . '/../public/files/tmp/' . basename($pdf['name']), 'cover.pdf');
            }
        }

        return '';
    }

    private function createBrochure(string $layout, string $brochureUrl): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setTitle($this->campaign['brochure_title'])
            ->setBrochureNumber($this->brochureNumber)
            ->setUrl($brochureUrl)
            ->setVariety('leaflet')
            ->setStart($this->campaign['start'])
            ->setVisibleStart($brochure->getStart())
            ->setEnd($this->campaign['end'])
            ->setLayout($layout);
    }
}
