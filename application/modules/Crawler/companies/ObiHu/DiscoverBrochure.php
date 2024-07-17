<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover brochure crawler for Obi HU (ID: 80858)
 */
class Crawler_Company_ObiHu_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # upload the cover_pages onto our ftp server (folder 80858/Discover)    #
        #                                                                       #
        # adjust the variables as needed                                        #
        #########################################################################

        $campaigns = [

            1 => [
                'article_url' => 'https://transport.productsup.io/447483ad41bc16ed4075/channel/385845/pdsfeed.csv',
                'campaign_title' => 'Obi újság lejárati dátum 13.03.',
                'campaign_pdf' => 'L0015_1303_FB_HU.pdf',
                'brochure_number_prefix' => 'L0015',
                'start_date' => '02.03.2022',
                'end_date' => '13.03.2022',
            ],

        ];
        

        # services
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

         $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sFtp->connect('80858');

        # create a list with active articles
        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        foreach ($campaigns as $campaign) {

            if (strtotime($campaign['end_date']) < time()) {
                $this->_logger->info("skipping campaign {$campaign['campaign_title']} - end date was {$campaign['end_date']}.");
                continue;
            }

            #get the campaign's cover page
            $localCoverPage = $sFtp->downloadFtpToDir($campaign['campaign_pdf'], $localPath);

            #get the campaign's article file
            $localArticleFile = $sHttp->getRemoteFile($campaign['article_url'], $localPath);

            if (!strlen($localArticleFile)) {
                $this->_logger->err($companyId . ': unable to get feed file: ' . $campaign['article_url']);
                continue;
            }

            $aData = $sPss->readFile($localArticleFile, TRUE, ',')->getElement(0)->getData();

            # build a list with categories and give them page numbers
            $aCategories = [];
            $pageIndex = 0;

            foreach ($aData as $singleRow) {
                $category = str_replace(['Fuerdőszoba', 'Epites'], ['Fürdőszoba', 'Építés'], $singleRow['category']);

                if (!isset($aCategories[$category])) {
                    $pageIndex++;
                    $aCategories[$category] = $pageIndex;
                }
            }


            $aNewGen = [];
            foreach ($aData as $singleRow) {

                $category = str_replace(['Fuerdőszoba', 'Epites'], ['Fürdőszoba', 'Építés'], $singleRow['category']);

                # check for missing article IDs
                if (!$aArticleIds['DISCOVER_' . $singleRow['article_number']]) {
                    $this->_logger->warn('missing article number ' . 'DISCOVER_' . $singleRow['article_number']);
                    continue;
                }

                $priority = rand(1, 3);
                $aNewGen[$aCategories[$category]]['page_metaphore'] = $category;
                $aNewGen[$aCategories[$category]]['products'][] = [
                    'product_id' => $aArticleIds['DISCOVER_' . $singleRow['article_number']],
                    'priority' => $priority,
                ];

            }
            ksort($aNewGen);

            # the service in the new framework
            $response = Blender::blendApi('80858', $aNewGen, $campaign['brochure_number_prefix'] . '_' . str_replace(['header-', '.pdf'], '', $campaign['campaign_pdf']));


            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                throw new Exception('blender api did not work out');
            }

            $strLayout = $response['body'];
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($campaign['campaign_title'])
                ->setBrochureNumber($campaign['brochure_number_prefix'] . '_' . str_replace(['header-', '.pdf'], '', $campaign['campaign_pdf']))
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
