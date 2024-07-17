<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Hofmeister (ID: 69717)
 */
class Crawler_Company_Hofmeister_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # upload the cover_pages onto our ftp server (folder 69717)             #
        #                                                                       #
        # adjust the variables as needed                                        #
        #########################################################################

        $campaigns = [
            1 => [
                'article_url' => 'https://hofmeister.de/backend/export/index/offerista-hofmeister.csv?feedID=66&hash=fe248f9a0c9ce24d602286351a3963a8',
                'campaign_title' => 'Auf den Sommer vorbereiten',
                'campaign_pdf' => 'Hofmeister_KW25.pdf',
                'campaign_categories' => '20210628_Discover-Data-Set_Hofmeister.xlsx',
                'brochure_number_prefix' => 'KW33',
                'start_date' => '22.06.2021',
                'end_date' => '30.09.2021',
            ]
        ];

        # services
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sFtp->connect($companyId);

        # create a list with active articles
        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        foreach($campaigns as $campaign) {

            #get the campaign's cover page
            $localCoverPage = $sFtp->downloadFtpToDir($campaign['campaign_pdf'] , $localPath);
            $localCategoryFile = $sFtp->downloadFtpToDir($campaign['campaign_categories'] , $localPath);


            $aData = $sPss->readFile($localCategoryFile, TRUE)->getElement(0)->getData();

            # build a list with categories and give them page numbers
            $aCategories = [];
            $pageIndex = 0;

            foreach ($aData as $singleRow) {

                if(!$singleRow['category'])
                    continue;

               $category = $singleRow['category'];

                if(!isset($aCategories[$category])) {

                    $pageIndex++;
                    $aCategories[$category] = $pageIndex;
                }
            }

            $aNewGen = [];
            foreach ($aData as $singleRow) {

                # check for missing article IDs
                if (!$aArticleIds[$singleRow['article_number']]) {
                    $this->_logger->warn('fehlende Artikelnummer ' . $singleRow['article_number']);
                    continue;
                }

                # build the discover array
                $priority = rand(1, 3);
                $aNewGen[$aCategories[$singleRow['category']]]['page_metaphore'] = $singleRow['category'];
                $aNewGen[$aCategories[$singleRow['category']]]['products'][] = [
                    'product_id' => $aArticleIds[$singleRow['article_number']],
                    'priority' => $priority,
                ];
            }
            ksort($aNewGen);

            # the service in the new framework
            $response = Blender::blendApi($companyId, $aNewGen, $campaign['brochure_number_prefix'] . '_' . str_replace(['header-','.pdf'], '' , $campaign['campaign_pdf']));


            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                throw new Exception('blender api did not work out');
            }

            $strLayout = $response['body'];
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Hofmeister: ' . $campaign['campaign_title'])
                ->setBrochureNumber($campaign['brochure_number_prefix'] . '_' . str_replace(['header-','.pdf'], '' , $campaign['campaign_pdf']))
                ->setUrl($localCoverPage)
                ->setVariety('leaflet')
                ->setStart($campaign['start_date'])
                ->setEnd($campaign['end_date'])
                ->setVisibleStart($eBrochure->getStart())
                ->setLayout($strLayout);

            $cBrochures->addElement($eBrochure);
        }
        return $this->getResponse($cBrochures, $companyId);
    }
}
