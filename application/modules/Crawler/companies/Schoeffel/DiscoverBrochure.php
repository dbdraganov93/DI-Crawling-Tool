<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * (ID: 71098)
 */
class Crawler_Company_Schoeffel_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $aCampaign = [
            1 => [
                'brochure_number' => 'KW47_2022',
                'start_date' => '22.11.2022',
                'end_date' => '10.12.2022',
                'title_page' => 'Discover Header.pdf',
                'campaign_title' => 'Wir sind Natur',
                'article_file' => 'file_november_articles.xlsx',

            ]
        ];

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cArticles = $sApi->getActiveArticleCollection($companyId);
        foreach ($cArticles->getElements() as $eArticle) {
            if (!preg_match('#_Disc$#', $eArticle->getArticleNumber())) {
                continue;
            }

            $aDiscoverArticles[preg_replace('#_Disc#', '', $eArticle->getArticleNumber())] = $eArticle->getArticleId();
        }


        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($aCampaign as $singleCampaign) {
            $localPath = $sFtp->connect($companyId, TRUE);
            foreach ($sFtp->listFiles() as $singleRemoteFile) {
                if (preg_match('#' . $singleCampaign['title_page'] . '#', $singleRemoteFile)) {
                    $localFrontPage = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                } elseif (preg_match('#' . $singleCampaign['article_file'] . '#', $singleRemoteFile)) {
                    $localAssignmentFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                }
            }

            $sFtp->close();

            $aData = $sPss->readFile($localAssignmentFile, TRUE)->getElement(0)->getData();

            $aNewGen = [];
            foreach ($aData as $singleRow) {
                $aNewGen[$singleRow['page']]['products'][] = [
                    'priority' => rand(1, 3),
                    'product_id' => $aDiscoverArticles[$singleRow['article_number']]
                ];
                $aNewGen[$singleRow['page']]['page_metaphore'] = $singleRow['category'];

                if(!$aDiscoverArticles[$singleRow['article_number']]) {
                    $this->_logger->warn('fehlende Artikelnummer ' . $singleRow['article_number']);
                }
            }

            ksort($aNewGen);

            $this->_logger->info("requesting Discover layout");
            $response = Blender::blendApi($companyId, $aNewGen, $singleCampaign['brochure_number']);

            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                throw new Exception('blender api did not work out');
            }

            $strLayout = $response['body'];
            $eBrochure = new Marktjagd_Entity_Api_Brochure();


            $eBrochure->setTitle($singleCampaign['campaign_title'])
                ->setBrochureNumber($singleCampaign['brochure_number'])
                ->setUrl($localFrontPage)
                ->setVariety('leaflet')
                ->setStart($singleCampaign['start_date'])
                ->setEnd($singleCampaign['end_date'])
                ->setVisibleStart($eBrochure->getStart())
                ->setLayout($strLayout);


            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}