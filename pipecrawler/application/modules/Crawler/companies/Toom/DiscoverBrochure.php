<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für toom (ID: 123)
 */
class Crawler_Company_toom_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # upload products and cover_page.pdf                                    #
        # onto our ftp server (folder 123/Discover)                             #
        #                                                                       #
        # adjust the variables as needed                                        #
        #########################################################################


        $categoryFile ='Discover Product Data Set toom OH-25.07.xlsx';
        $campaignName = 'Online Hammer KW29';
        $visibleStart = '22.07.2021';
        $startDate = '25.07.2021';
        $endDate = '25.07.2021 23:59:59';
        $titlePage = [
            'Mon' => 'Do-Sa',
            'Tue' => 'Do-Sa',
            'Wed' => 'Do-Sa',
            'Thu' => 'Do-Sa',
            'Fri' => 'Do-Sa',
            'Sat' => 'Do-Sa',
            'Sun' => 'So'
        ];

        $weekday = date('D');
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();


        $localFolder = $sFtp->connect('123', TRUE);
        $sFtp->changedir('Discover');
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#[^\.]+'.$titlePage[$weekday].'\.pdf#', $singleFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }
        }

        $localCategoryFile = $sFtp->downloadFtpToDir(  $categoryFile , $localFolder);
        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $aData = $sPss->readFile($localCategoryFile, TRUE)->getElement(0)->getData();

        $aNewGen = [];
        foreach ($aData as $singleRow) {

            if(!$aArticleIds['DISCOVER_' . $singleRow['article_number']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . 'DISCOVER_' . $singleRow['article_number']);
                continue;
            }


            $aNewGen[1]['page_metaphore'] = 'Online Hammer';
            $aNewGen[1]['products'][] = [
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


        $eBrochure->setTitle('toom: Online Hammer')
            ->setBrochureNumber($campaignName)
            ->setUrl($localBrochurePath)
            ->setVariety('leaflet')
            ->setStart($startDate)
            ->setEnd($endDate)
            ->setVisibleStart($visibleStart)
            ->setNational(1)
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

}