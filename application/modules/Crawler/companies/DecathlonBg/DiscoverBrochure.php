<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover brochure crawler for Decathlon Bulgaria (ID: 82612)
 */
class Crawler_Company_DecathlonBg_DiscoverBrochure extends Crawler_Generic_Company
{
    private Marktjagd_Service_Input_GoogleSpreadsheetRead $sGSRead;
    private const COVER_PAGE_FOLDER_ID = '1UWq2gy4mA9rIFaXk7gdy9Iz6H1XbB-qe';
    private int $companyId;

    function crawl($companyId)
    {
        $this->sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $this->companyId = $companyId;
        $aCampaignInfos = $this->getLastOfferData();

        $aArticles = $this->getArticles($aCampaignInfos);
        $localCoverFile = $this->getCoverPage($aCampaignInfos['coverPage']);

        $this->_logger->info("requesting Discover layout");
        $response = Blender::blendApi($this->companyId, $aArticles, $aCampaignInfos['brochureNumber']);

        if (200 != $response['http_code']) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = $this->createBrochure($aCampaignInfos, $localCoverFile, $response['body']);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures);
    }

    /**
     * @param array|null $aCampaignInfos
     * @param int $companyId
     * @return array
     * @throws Exception
     */
    public function getArticles(?array $aCampaignInfos): array
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $articleInfos = $this->sGSRead->getFormattedInfos($aCampaignInfos['spreadsheetId'], 'A1', 'W');

        $aArticles = [];
        foreach ($articleInfos as $singleArticle) {
            $aArticles[$singleArticle['pageNumber'] - 1]['page_metaphor'] = $singleArticle['category'];
            $aArticles[$singleArticle['pageNumber'] - 1]['products'][] = [
                'product_id' => $sApi->findArticleByArticleNumber($this->companyId, $singleArticle['articleNumber'] . '_Disc_' . date('W_Y', strtotime($aCampaignInfos['start'])))['id'],
                'priority' => rand(1, 3)
            ];
        }
        return $aArticles;
    }

    /**
     * @param array|null $aCampaignInfos
     * @param string $localCoverFile
     * @param string $body
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function createBrochure(?array $aCampaignInfos, string $localCoverFile, string $body): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle($aCampaignInfos['brochureTitle'])
            ->setBrochureNumber($aCampaignInfos['brochureNumber'])
            ->setUrl($localCoverFile)
            ->setVariety('leaflet')
            ->setStart($aCampaignInfos['start'])
            ->setEnd($aCampaignInfos['end'])
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($body);

        return $eBrochure;
    }

    public function getLastOfferData(): array
    {
        $offersData = $this->sGSRead->getFormattedInfos('1fDgXOh3RjKwBa0ojgHORzvmvPAl4MStJjwd5LPpwPlA', 'A1', 'G', 'DecathlonBg');

        $selectedOffer = end($offersData);

        if (empty($selectedOffer)) {
            throw new Exception('Company ID: ' . $this->companyId . ': No offer found!');
        }

        return $selectedOffer;
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
        $file = $googleDriveService->downloadFile($caverPageId, $localPath, 'cover.pdf');

        return $ftp->generatePublicFtpUrl($file);
    }
}
