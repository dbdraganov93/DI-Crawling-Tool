<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover Brochure Crawler for Hartlauer AT (ID: 73468)
 */
class Crawler_Company_HartlauerAt_DiscoverBrochure extends Crawler_Generic_Company
{
    private const BROCHURE_NUMBER_SUFFIX = '_HartlauerAt';

    private array $campaignData;
    private int $companyId;
    private Crawler_Company_HartlauerAt_DiscoverHelpers $helpers;

    public function __construct()
    {
        parent::__construct();

        $this->helpers = new Crawler_Company_HartlauerAt_DiscoverHelpers();
        $this->campaignData = $this->helpers->getCampaignData();
    }

    public function crawl($companyId)
    {
        if (empty($this->campaignData)) {
            return $this->setResponseIfNoImport($companyId);
        }

        $this->companyId = $companyId;

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($this->campaignData as $campaign) {
            $brochureData = $this->getBrochureData($campaign);
            $brochure = $this->createBrochure($brochureData);
            $brochures->addElement($brochure);
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getBrochureData(array $campaign): array
    {
        $brochureNumber = $campaign['numberPrefix'] . str_replace('.', '', $campaign['start']) . self::BROCHURE_NUMBER_SUFFIX;
        $layout = $this->generateLayout($campaign, $brochureNumber);

        return [
            'title' => $campaign['title'],
            'number' => $brochureNumber,
            'url' => $campaign['coverPageURL'],
            'start' => $campaign['start'],
            'end' => $campaign['end'],
            'zipcodes' => $campaign['zipcodes'],
            'layout' => $layout
        ];
    }

    private function generateLayout(array $campaign, string $brochureNumber): string
    {
        $productFeed = $this->helpers->getProductFeedData($campaign['productFeed'], $this->companyId);
        $articleIds = $this->getAllActiveArticleIds();

        $pagesData = [];
        foreach ($productFeed as $articleData) {
            $articleNumber = $this->helpers->getArticleNumber($articleData['articleNumber'], $campaign);
            if (!isset($articleIds[$articleNumber])) {
                $this->_logger->err('Company ID: ' . $this->companyId . ': Article with number ' . $articleNumber . ' not found!');
                continue;
            }

            $pageNumber = $articleData['pageNumber'];
            if (empty($pageNumber)) {
                $this->_logger->err('Company ID: ' . $this->companyId . ': Article with number ' . $articleNumber . ' has no page number!');
                continue;
            }

            $pagesData[$pageNumber]['products'][] = [
                'priority' => rand(1, 3),
                'product_id' => $articleIds[$articleNumber]
            ];
            $pagesData[$pageNumber]['page_metaphor'] = $articleData['category'];
        }

        ksort($pagesData);

        $response = Blender::blendApi($this->companyId, $pagesData, $brochureNumber);

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
            ->setVisibleStart($brochure->getStart())
            ->setEnd($brochureData['end'])
            ->setLayout($brochureData['layout'])
            ->setZipCode($brochureData['zipcodes'])
            ->setVariety('leaflet');
    }
}
