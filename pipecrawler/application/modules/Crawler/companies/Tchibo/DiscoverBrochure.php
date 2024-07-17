<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Tchibo (ID: 25)
 */
class Crawler_Company_Tchibo_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # upload products, products_category_mapping and cover_page.pdf         #
        # onto our ftp server (folder 25/Discover)                              #
        #                                                                       #
        # adjust the variables ad needed                                        #
        #########################################################################


        $categoryFile = 'discover products - Tchibo - 2021-04-15_2.csv';
        $campaignName = 'tchibo AWIN Discover 2021-04-19';
        $startDate = '19.04.2021';
        $endDate = '31.05.2021';

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localFolder = $sFtp->connect($companyId, TRUE);
        $sFtp->changedir('Discover');
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#[^\.]+\.pdf#', $singleFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }
        }

        $localCategoryFile = $sFtp->downloadFtpToDir(  $categoryFile , $localFolder);
        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $aData = $sPss->readFile($localCategoryFile, TRUE, ',')->getElement(0)->getData();

        $aNewGen = [];
        foreach ($aData as $singleRow) {

            if(!$aArticleIds['DISCOVER_' . $singleRow['article_number']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . 'DISCOVER_' . $singleRow['article_number']);
                continue;
            }


            $aNewGen[$singleRow['category_order']]['page_metaphore'] = $singleRow['category'];
            $aNewGen[$singleRow['category_order']]['products'][] = [
                'product_id' => $aArticleIds['DISCOVER_' . $singleRow['article_number']],
                'priority' => $singleRow['layout_priority'],
            ];

        }

        $response = Blender::blendApi($companyId, $aNewGen, $campaignName);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();


        $eBrochure->setTitle('Tchibo: Wochenangebote')
            ->setBrochureNumber($campaignName)
            ->setUrl($localBrochurePath)
            ->setVariety('leaflet')
            ->setStart($startDate)
            ->setEnd($endDate)
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        # move the PDF file after the creation of the Discover
        #$sFtp->move('./' . $pdfFile, './archive/' . $pdfFile);
        #$sFtp->close();

        return $this->getResponse($cBrochures, $companyId);
    }

}