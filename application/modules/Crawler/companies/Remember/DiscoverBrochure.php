<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover brochure crawler for Remember (ID: 82393)
 */
class Crawler_Company_Remember_DiscoverBrochure extends Crawler_Generic_Company
{
    private Marktjagd_Service_Transfer_FtpMarktjagd $sFtp;

    public function crawl($companyId)
    {
        $aCampaigns = [
            1 => [
                'startDate' => '28.11.2022',
                'endDate' => '31.12.2022',
                'campaignTitle' => 'Farbenfrohes Wohndesign',
                'brochurePrefix' => 'Remember_122022',
                'titlePage' => '220930Online-Prospekt_Titel_final.pdf',
                'articleFile' => 'Remember Produktauswahl Offerista_new.xlsx',

            ]
        ];

        $this->sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $localPath = $this->sFtp->connect($companyId, TRUE);
        $aArticleIds = $this->getActiveArticleIds($companyId);

        foreach ($aCampaigns as $singleCampaign) {

            $localFiles = $this->getLocalFiles($singleCampaign, $localPath);

            $aData = $sPss->readFile($localFiles['articleFile'], TRUE)->getElement(0)->getData();

            $productPages = $this->generateProductPages($aData, $aArticleIds);

            $strLayout = $this->generateLayout($companyId, $productPages, $singleCampaign['brochurePrefix']);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($singleCampaign['campaignTitle'])
                ->setBrochureNumber($singleCampaign['brochurePrefix'])
                ->setUrl($localFiles['titlePageFile'])
                ->setVariety('leaflet')
                ->setStart($singleCampaign['startDate'])
                ->setEnd($singleCampaign['endDate'])
                ->setVisibleStart($eBrochure->getStart())
                ->setLayout($strLayout);

            $cBrochures->addElement($eBrochure);
        }

        $this->sFtp->close();

        return $this->getResponse($cBrochures);
    }

    private function getLocalFiles(array $singleCampaign, string $localPath): array
    {
        $localFiles = [];

        foreach ($this->sFtp->listFiles('./Discover') as $singleRemoteFile) {
            if (preg_match('#' . $singleCampaign['titlePage'] . '#', $singleRemoteFile)) {
                $localFiles['titlePageFile'] = $this->sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            } elseif (preg_match('#' . $singleCampaign['articleFile'] . '#', $singleRemoteFile)) {
                $localFiles['articleFile'] = $this->sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        if (!isset($localFiles['titlePageFile'])) {
            throw new Exception('Title page "'.$singleCampaign['titlePage'].'" was not found in folder: ' . $localPath);
        }
        if (!isset($localFiles['articleFile'])) {
            throw new Exception('Article list "'.$singleCampaign['articleFile'].'" was not found in folder: ' . $localPath);
        }

        return $localFiles;
    }

    private function getActiveArticleIds(int $companyId): array
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $aApiData = $sApi->getActiveArticleCollection($companyId);

        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[preg_replace('#_Disc#', '', $eApiData->getArticleNumber())] = $eApiData->getArticleId();
        }

        return $aArticleIds;
    }

    private function generateProductPages(array $aData, array $aArticleIds): array
    {
        $aNewGen= [];
        foreach ($aData as $singleRow) {
            $aNewGen[$singleRow['category']]['page_metaphor'] = $singleRow['category'];
            $aNewGen[$singleRow['category']]['products'][] = [
                'product_id' => $aArticleIds[$singleRow['article_number']],
                'priority' => $singleRow['layout_priority'],
            ];
        }

        return array_values($aNewGen);
    }

    private function generateLayout(int $companyId, array $productPages, string $brochurePrefix): string
    {
        $response = Blender::blendApi($companyId, $productPages, $brochurePrefix);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        return $response['body'];
    }
}