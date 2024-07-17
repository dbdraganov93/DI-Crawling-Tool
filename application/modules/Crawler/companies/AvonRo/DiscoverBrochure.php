<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover Crawler for AVON RO (ID: 80414 )
 */
class Crawler_Company_AvonRo_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # Update the article data in the google sheet and modify the variable   #
        # Upload the cover page onto our ftp server (folder 80414/Discover)     #
        #                                                                       #
        #########################################################################


        $gSheetId = '19z60J7X6UZ6xIxNc8zuS3c3CZKPSLpyBY2nqm53Ou3U';
        $visibleStart = '06.12.2021';
        $start = '06.12.2021';
        $end = '31.12.2021 23:59:59';
        $brochureTitle = 'AVON';
        $brochureNumber = 'Avon_Discover_dec_2021';
        $coverPage = 'cover Discover.pdf';

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sGSheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $localFolder = $sFtp->connect($companyId, TRUE);
        $localBrochurePath = $sFtp->downloadFtpToDir('./' . $coverPage, $localFolder);

        $articleList = $sGSheet->getFormattedInfos($gSheetId, 'A2', 'N', 'Discover Data');

        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        foreach ($articleList as $singleRow) {
            $categories[] = $singleRow['category'];
        }
        $categories = array_unique($categories);
        $categories = array_values($categories);

        $aDiscover = [];
        foreach ($articleList as $singleRow) {

            if(!$aArticleIds['D_' . $singleRow['article_number']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . 'D_' . $singleRow['article_number']);
                continue;
            }

            $pageNumber = array_keys($categories, $singleRow['category'])[0];

            $aDiscover[$pageNumber]['page_metaphore'] = $singleRow['category'];
            $aDiscover[$pageNumber]['products'][] = [
                'product_id' => $aArticleIds['D_' . $singleRow['article_number']],
                'priority' => rand(1,3),
            ];

        }


        $response = Blender::blendApi($companyId, $aDiscover, $brochureNumber);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender API did not work out');
        }

        $strLayout = $response['body'];
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();


        $eBrochure->setTitle($brochureTitle)
            ->setBrochureNumber($brochureNumber)
            ->setUrl($localBrochurePath)
            ->setVariety('leaflet')
            ->setStart($start)
            ->setEnd($end)
            ->setVisibleStart($eBrochure->getStart())
            ->setNational('1')
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        # move the PDF file after the creation of the Discover
        #$sFtp->move('./' . $pdfFile, './archive/' . $pdfFile);
        #$sFtp->close();

        return $this->getResponse($cBrochures, $companyId);
    }

}