<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover Brochure Crawler für Media Markt AT (ID: 73214)
 */
class Crawler_Company_MediaMarktAt_DiscoverBrochure extends Crawler_Generic_Company
{
    private Crawler_Company_MediaMarktAt_DiscoverHelpers $helpers;
    private int $companyId;
    private array $campaignData;

    public function __construct()
    {
        parent::__construct();
    }

    public function crawl($companyId)
    {
        ini_set('memory_limit', '4G');
        $this->helpers = new Crawler_Company_MediaMarktAt_DiscoverHelpers();
        $this->campaignData = $this->helpers->getCampaignData();
        $this->companyId = $companyId;
        $brochures = new Marktjagd_Collection_Api_Brochure();

        $brochureData = $this->getBrochureData();
        $aUrl = [];
        foreach ($brochureData as $singleBrochureData) {
            if (array_key_exists($singleBrochureData['number'], $aUrl)) {
                $singleBrochureData['url'] = $aUrl[$singleBrochureData['number']];
            }
            $brochure = $this->createBrochure($singleBrochureData);
            $brochures->addElement($brochure);
            $aUrl[$brochure->getBrochureNumber() . '_dup'] = $brochure->getUrl();
        }

        return $this->getResponse($brochures);
    }

    private function getBrochureData(): array
    {
        foreach ($this->campaignData as $row => $singleCampaign) {
            $brochureNumber = $this->helpers->getBrochureNumber($singleCampaign);
            $campaignFiles = $this->helpers->downloadCampaignFiles($this->companyId, $singleCampaign);
            $singleCampaign['duplicate'] = FALSE;
            $articlesData = $this->helpers->getArticlesData($singleCampaign, $campaignFiles['productFeed'], $campaignFiles['articleListFile']);
            $layout = $this->generateLayout($articlesData, $brochureNumber);

            $brochureData[] = [
                'title' => $singleCampaign['title'],
                'number' => $brochureNumber . '_' . $row,
                'url' => $campaignFiles['coverPagePdf'],
                'start' => $singleCampaign['validityStart'],
                'visibleStart' => $singleCampaign['validityStart'],
                'end' => $singleCampaign['validityEnd'],
                'trackingBug' => $singleCampaign['trackingBug'],
                'layout' => $layout
            ];
            if (preg_match('#x#', $singleCampaign['needsDuplicate'])) {
                $singleCampaign['duplicate'] = TRUE;
                $articlesData = $this->helpers->getArticlesData($singleCampaign, $campaignFiles['productFeed'], $campaignFiles['articleListFile']);
                $layout = $this->generateLayout($articlesData, $brochureNumber);
                $brochureData[] = [
                    'title' => $singleCampaign['title'],
                    'number' => $brochureNumber . '_' . $row . '_dup',
                    'url' => $campaignFiles['coverPagePdf'],
                    'start' => $singleCampaign['validityStart'],
                    'visibleStart' => $singleCampaign['validityStart'],
                    'end' => $singleCampaign['validityEnd'],
                    'trackingBug' => $singleCampaign['trackingBug'],
                    'layout' => $layout
                ];
            }
        }

        return $brochureData;
    }

    private function generateLayout(array $articlesData, string $brochureNumber): string
    {
        $articleIds = $this->getAllActiveArticleIds();

        $pages = [];
        foreach ($articlesData as $articleData) {
            if (empty($articleIds[$articleData['number']])) {
                $this->_logger->err("Company ID: {$this->companyId}: Article {$articleData['number']} not found in active articles");
                continue;
            }

            $category = $articleData['category'];
            $pages[$category]['page_metaphor'] = $category;
            $pages[$category]['products'][] = [
                'priority' => rand(1, 3),
                'product_id' => $articleIds[$articleData['number']]
            ];
        }
        $pages = array_values($pages);

        $response = Blender::blendApi($this->companyId, $pages, $brochureNumber);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        return $response['body'];
    }

    private function getAllActiveArticleIds(): array
    {
        $api = new Marktjagd_Service_Input_MarktjagdApi();
        $activeArticles = $api->getActiveArticleCollection($this->companyId)->getElements();

        $articleIds = [];
        foreach ($activeArticles as $article) {
            $articleIds[$article->getArticleNumber()] = $article->getArticleId();
        }

        return $articleIds;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setTitle($brochureData['title'])
            ->setBrochureNumber($brochureData['number'])
            ->setUrl($brochureData['url'])
            ->setStart($brochureData['start'])
            ->setVisibleStart($brochureData['visibleStart'])
            ->setEnd($brochureData['end'])
            ->setTrackingBug($brochureData['trackingBug'])
            ->setLayout($brochureData['layout']);
    }
}
