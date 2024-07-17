<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für IKEA BG (ID: 80534)
 */
class Crawler_Company_IkeaBg_DiscoverBrochure extends Crawler_Generic_Company
{
    private const COVER_PAGE_FOLDER_ID = '1TVL5zwLEl368Q00BCfe0y972gMxbMuOG';

    private Crawler_Company_IkeaBg_DiscoverHelpers $helpers;
    private int $companyId;

    public function __construct()
    {
        parent::__construct();

        $this->helpers = new Crawler_Company_IkeaBg_DiscoverHelpers();
    }

    public function crawl($companyId)
    {
        $this->companyId = $companyId;

        $offers = $this->helpers->getActiveOffersData($companyId);

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($offers as $offerData) {
            $brochureData = $this->getBrochureData($offerData);
            $brochure = $this->createBrochure($brochureData);
            $brochures->addElement($brochure);
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getBrochureData(array $offer): array
    {
        $articlesToUse = $this->getArticlesToUse($offer);
        $layout = $this->createLayout($articlesToUse, $offer['number']);
        $cover = $this->getCoverPage($offer['cover_page']);

        return [
            'title' => $offer['brochure_title'],
            'brochure_number' => $offer['number'],
            'url' => $cover,
            'variety' => 'leaflet',
            'start' => $offer['start'],
            'end' => $offer['end'],
            'visible_start' => $offer['start'],
            'national' => 1,
            'layout' => $layout
        ];
    }

    private function getActiveArticleIDs(string $brochureNumber): array
    {
        $api = new Marktjagd_Service_Input_MarktjagdApi();

        $activeArticles = $api->getActiveArticleCollection($this->companyId);
        $articleIds = [];
        foreach ($activeArticles->getElements() as $article) {
            $articleNumber = $article->getArticleNumber();
            if (preg_match('#^' . $brochureNumber . '#', $articleNumber)) {
                $articleIds[$articleNumber] = $article->getArticleId();
            }
        }

        return $articleIds;
    }

    private function getArticlesToUse(array $offer): array
    {
        $articleIds = $this->getActiveArticleIDs($offer['number']);
        $products = $this->helpers->getProducts($offer);

        $articlesToUse = [];
        foreach ($products as $product) {
            $articleNumber = $this->helpers->createArticleNumber($offer['number'], $product['id']);
            if (!empty($articleIds[$articleNumber])) {
                $articlesToUse[] = $articleIds[$articleNumber];
            }
        }

        return $articlesToUse;
    }

    private function createLayout(array $articleIds, string $brochureNumber): string
    {
        $productPages = [];
        $page = 1;
        foreach ($articleIds as $articleId) {
            $productPages[$page]['products'][] = [
                'priority' => rand(1, 3),
                'product_id' => $articleId
            ];
        }
        $productPages[$page]['page_metaphore'] = 'Страница ' . $page;

        ksort($productPages);

        $response = Blender::blendApi($this->companyId, $productPages, $brochureNumber);

        if (200 !== $response['http_code']) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        return $response['body'];
    }

    private function getCoverPage(string $pdfName): string
    {
        $googleDriveService = new Marktjagd_Service_Google_Drive();
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $covers = $googleDriveService->getFiles(self::COVER_PAGE_FOLDER_ID);

        foreach ($covers as $cover) {
            if ($cover['name'] === $pdfName) {
                $caverPageId = $cover['id'];
            }
        }

        if (empty($caverPageId)) {
            throw new Exception($this->companyId . ': No cover page found!');
        }

        $localPath = $ftp->connect($this->companyId, TRUE);
        $file = $googleDriveService->downloadFile($caverPageId, $localPath , 'cover.pdf');

        return $ftp->generatePublicFtpUrl($file);
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setTitle($brochureData['title'])
            ->setBrochureNumber($brochureData['brochure_number'])
            ->setUrl($brochureData['url'])
            ->setVariety($brochureData['variety'])
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochureData['visible_start'])
            ->setNational($brochureData['national'])
            ->setLayout($brochureData['layout']);
    }
}
