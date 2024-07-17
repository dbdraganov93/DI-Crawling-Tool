<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Dm (ID: 27)
 */
class Crawler_Company_Dm_DiscoverBrochure extends Crawler_Generic_Company
{
    private const TRACKING_URL = 'https://www.dm.de/?wt_mc=display.angebots-apps.always-on-offerista.discover&hc_tid=10217192C4751PPC&cb=%%CACHEBUSTER%%';
    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private Marktjagd_Service_Input_MarktjagdApi $api;
    private Marktjagd_Service_Input_PhpSpreadsheet $spreadsheetReader;
    private array $campaignData;
    private int $companyId;
    private string $specialCategory;
    private string $numberPrefix;

    public function __construct()
    {
        parent::__construct();
        ini_set('error_reporting', E_NOTICE);
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->api = new Marktjagd_Service_Input_MarktjagdApi();
        $this->spreadsheetReader = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $this->campaignData = $sGSRead->getCustomerData('dmGer');
        $this->numberPrefix = Crawler_Company_Dm_DiscoverArticle::getNumberPrefix($this->campaignData['brochure_number'], $this->campaignData['start_date']);
    }

    public function crawl($companyId)
    {
        ini_set('memory_limit', '6G');
        $this->companyId = $companyId;
        $brochureData = [];
        $brochures = new Marktjagd_Collection_Api_Brochure();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $this->localPath = $this->ftp->connect($companyId, TRUE);
        $this->ftp->changedir('Discover');

        if (strtotime($this->campaignData['end_date']) < time()) {
            $message = "valid end date reached for campaign: {$this->campaignData['brochure_number']} SKIPPING CAMPAIGN";
            $this->_logger->info($message);
            throw new Exception($message);
        }

        $this->_logger->info("processing campaign: {$this->campaignData['brochure_number']}");

        $brochureData['coverPage'] = $this->downloadCoverPage();
        $brochureData['categoryFile'] = $this->downloadCategoryFile();
        $this->ftp->close();

        $categories = explode(', ', $this->campaignData['categories']);
        $this->specialCategory = reset($categories);

        $aData = $sPss->readFile($brochureData['categoryFile'], TRUE)->getElement(0)->getData();
        foreach ($aData as $singleRow) {
            $articles[$singleRow['category']][] = $this->findArticle($singleRow['articleNumber'])['id'];
        }
        $brochureData['layout'] = $this->createLayout($categories, $articles);

        $brochure = $this->createBrochure($brochureData);

        $brochures->addElement($brochure);

        return $this->getResponse($brochures, $companyId);
    }

    /**
     * @throws Exception
     */
    private function downloadCoverPage(): string
    {
        $this->_logger->info('downloading cover page');
        $coverPage = $this->ftp->downloadFtpToDir($this->campaignData['cover_page'], $this->localPath);
        if (!$coverPage) {
            throw new Exception("unable to download cover page for campaign: {$this->campaignData['brochure_number']} SKIPPING CAMPAIGN");
        }

        return $coverPage;
    }

    /**
     * @throws Exception
     */
    private function findArticle(string $articleNumber): array
    {
        $this->_logger->info("querying article from API, article_number: {$articleNumber}.");
        $article = strtotime($this->campaignData['start_date']) <= time() ?
            $this->api->findArticleByArticleNumber($this->companyId, $articleNumber) :
            $this->api->findUpcomingArticleByNumber($this->companyId, $articleNumber);
        if (empty($article['id'])) {
            throw new Exception("SKIPPING ARTICLE: error querying article from API, article_number: {$articleNumber}.");
        }

        return $article;
    }

    /**
     * @throws Exception
     */
    private function createLayout(array $categories, array $articles): string
    {
        $this->_logger->info("preparing Blender request");
        $discover = [];
        foreach ($categories as $category) {
            $products = [];
            foreach ($articles[$category] as $articleIds) {
                $products[] = [
                    'product_id' => $articleIds,
                    'priority' => rand(1, 3)
                ];
            }

            if (empty($products)) {
                $this->_logger->info("SKIPPING CATEGORY: {$category} no products found");
                continue;
            }

            $discover[] = [
                'page_metaphor' => $category,
                'products' => $products
            ];
        }
        $this->_logger->info("requesting Discover layout");
        $response = Blender::blendApi($this->companyId, $discover, $this->campaignData['brochure_number']);

        if (200 != $response['http_code']) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        return $response['body'];
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        return $eBrochure->setTitle($this->campaignData['title'])
            ->setBrochureNumber($this->campaignData['brochure_number'])
            ->setUrl($brochureData['coverPage'])
            ->setVariety('leaflet')
            ->setStart($this->campaignData['start_date'])
            ->setEnd($this->campaignData['end_date'])
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($brochureData['layout'])
            ->setTrackingBug(self::TRACKING_URL);
    }

    private function downloadCategoryFile()
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->connect($this->companyId, TRUE);
        $localFile = $sFtp->downloadFtpToDir('./Discover/disc_' . $this->numberPrefix . '.csv', $localPath);
        $sFtp->close();

        return $localFile;
    }
}
