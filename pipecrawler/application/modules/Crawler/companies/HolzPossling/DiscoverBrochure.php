<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

use Crawler_Company_HolzPossling_DiscoverHelpers as DiscoverHelpers;

/**
 * NewGen Brochure Crawler für Holz Possling (ID: 71464)
 */
class Crawler_Company_HolzPossling_DiscoverBrochure extends Crawler_Generic_Company
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

        $brochures = new Marktjagd_Collection_Api_Brochure();

        $brochureData = $this->getBrochureData();
        $brochure = $this->createBrochure($brochureData);
        $brochures->addElement($brochure);

        return $this->getResponse($brochures, $companyId);
    }

    private function getBrochureData(): array
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $ftp->connect(DiscoverHelpers::DEFAULT_FTP_FOLDER);
        $localPath = $ftp->generateLocalDownloadFolder($this->companyId);

        $localCoverPage = $ftp->downloadFtpToDir('./discover/' . $this->campaignData['brochureFile'], $localPath);

        return [
            'title' => 'Holz Possling: Discover',
            'number' => $this->campaignData['brochureNumber'],
            'url' => $localCoverPage,
            'start' => $this->campaignData['validStart'],
            'end' => $this->campaignData['validEnd'],
            'visibleStart' => $this->campaignData['validStart'],
            'layout' => $this->generateLayout()
        ];

    }

    private function generateLayout(): string
    {
        $activeArticleIds = $this->getActiveArticleIds();

        $pages = [];
        foreach ($this->articleFeed as $articleData) {
            if (empty($articleData['ArtNr']))
                continue;

            $articleNumber = $this->campaignData['brochureNumber'] . '_' . $articleData['ArtNr'];

            if (!$activeArticleIds[$articleNumber]) {
                $this->_logger->warn('fehlende Artikelnummer ' . $articleNumber);
                continue;
            }

            $pages[$articleData['Kategorie']]['page_metaphor'] = $articleData['Kategorie'];
            $priority = $articleData['Priorität'];
            if (!$articleData['Priorität']) {
                $priority = rand(2,3);
            }
            $pages[$articleData['Kategorie']]['products'][] = [
                'product_id' => $activeArticleIds[$articleNumber],
                'priority' => $priority,
            ];
        }

        $pages = array_values($pages);

        $response = Blender::blendApi($this->companyId, $pages, $this->campaignData['brochureNumber']);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        return $response['body'];
    }

    private function getActiveArticleIds(): array
    {
        $api = new Marktjagd_Service_Input_MarktjagdApi();
        $activeArticles = $api->getActiveArticleCollection($this->companyId);

        $aArticleIds = [];
        foreach ($activeArticles->getElements() as $article) {
            $aArticleIds[$article->getArticleNumber()] = $article->getArticleId();
        }

        return $aArticleIds;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setTitle($brochureData['title'])
            ->setBrochureNumber($brochureData['number'])
            ->setUrl($brochureData['url'])
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochureData['visibleStart'])
            ->setLayout($brochureData['layout'])
            ->setVariety('leaflet');
    }
}
