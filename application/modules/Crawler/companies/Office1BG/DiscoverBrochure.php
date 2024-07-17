<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

use Crawler_Company_Office1BG_DiscoverArticle as DiscoverArticle;

/**
 * Discover Crawler for Office1BG (ID: 80516)
 */
class Crawler_Company_Office1BG_DiscoverBrochure extends Crawler_Generic_Company
{
    private const WEEK = 'next';
    private const DATE_FORMAT = 'd.m.Y';
    private const GOOGLE_DRIVE_FOLDER = 'https://drive.google.com/drive/folders/1WHCDvN81VraWu7TYRLimzh0gmXSnimAv';
    protected string $kw;
    protected Marktjagd_Service_Text_Times $times;

    public function __construct()
    {
        parent::__construct();
        $this->times = new Marktjagd_Service_Text_Times();
        $this->kw = $this->_weekNr = $this->times->getWeekNr(self::WEEK);
    }

    public function crawl($companyId)
    {
        try {
            $cBrochures = new Marktjagd_Collection_Api_Brochure();

            $brochureData = [];
            $brochureData['startDate'] = date(self::DATE_FORMAT, strtotime('monday ' . self::WEEK . ' week'));
            // need to start from sunday but BT import date and chang it to monday, because of that we changed to saturday
//            $brochureData['endDate'] = date(self::DATE_FORMAT, strtotime('saturday ' . self::WEEK . ' week')) . ' 23:59:59';
            $brochureData['endDate'] = date(self::DATE_FORMAT,strtotime('+1 week',strtotime('saturday ' . self::WEEK . ' week'))) . ' 23:59:59';

            $articleIds = $this->getActiveArticleIds($companyId);
            $discoverArticles = $this->getDiscoverData();
            $brochureData['brochureNumber'] = 'DC_Office1_KW' . $this->kw;
            $brochureData['layout'] = $this->crateLayout($companyId, $articleIds, $discoverArticles, $brochureData['brochureNumber']);
            $brochureData['coverPage'] = $this->getCoverPageFromGoogleDrive();

            $eBrochure = $this->crateBrochure($brochureData);
            $cBrochures->addElement($eBrochure);
        } catch (\Exception $ex) {
            $this->_logger->err($ex->getMessage());
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    protected function getDiscoverData(): array
    {
        $googleReader = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        return $googleReader->getFormattedInfos(DiscoverArticle::DISCOVER_ARTICLES, 'A1', 'W');
    }

    /**
     * @throws Exception
     */
    private function crateLayout(int $companyId, array $articleIds, array $discoverArticles, string $brochureNumber): string
    {
        $aNewGen = [];
        foreach ($discoverArticles as $article) {
            if (!$articleIds[DiscoverArticle::DISCOVER_ARTICLE_PREFIX . $article['articleNumber']]) {
                $this->_logger->warn('missing article number ' . DiscoverArticle::DISCOVER_ARTICLE_PREFIX . $article['articleNumber']);
                continue;
            }

            $aNewGen[$article['pageNumber']]['page_metaphore'] = $article['category'] ?: $article['pageNumber'];
            $aNewGen[$article['pageNumber']]['products'][] = [
                'product_id' => $articleIds[DiscoverArticle::DISCOVER_ARTICLE_PREFIX . $article['articleNumber']],
                'priority' => (int) $article['priority'] ?: 1,
            ];

        }

        ksort($aNewGen);
        $response = Blender::blendApi($companyId, $aNewGen, $brochureNumber);


        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        return $response['body'];
    }

    private function getActiveArticleIds(int $companyId): array
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        return $aArticleIds;
    }

    /**
     * @throws Exception
     */
    private function getCoverPageFromGoogleDrive(): string
    {
        $googleDrive = new Marktjagd_Service_Input_GoogleDriveRead();
        $files = $googleDrive->readDrive(self::GOOGLE_DRIVE_FOLDER);

        foreach ($files as $fileId => $fileName) {
            if (preg_match('#[^\.]*\.pdf$#', $fileName, $distMatch)) {
                $coverPage = $googleDrive->downloadFile($fileId, APPLICATION_PATH . '/../public/files/tmp/' . basename($fileName));
                break;
            }
        }

        if (!$coverPage) {
            $this->_logger->err("No pdf-files on Google Drive for KW $this->kw");
            throw new Exception("No pdf-files on Google Drive for KW $this->kw");
        }

        return $coverPage;
    }

    private function crateBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        $brochure->setTitle('Седмична офертомания в Office 1')
            ->setBrochureNumber($brochureData['brochureNumber'])
            ->setUrl($brochureData['coverPage'])
            ->setVariety('leaflet')
            ->setStart($brochureData['startDate'])
            ->setEnd($brochureData['endDate'])
            ->setVisibleStart($brochure->getStart())
            ->setLayout($brochureData['layout']);

        return $brochure;
    }
}
