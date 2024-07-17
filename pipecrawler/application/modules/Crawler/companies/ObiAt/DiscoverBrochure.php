<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Obi AT (ID: 73321)
 */
class Crawler_Company_ObiAt_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # upload the cover_pages onto our ftp server (folder 73321/Discover)       #
        #                                                                       #
        # adjust the variables as needed                                        #
        #########################################################################

        $campaigns = [
            47 => [
                'article_url' => 'https://transport.productsup.io/ca804f076538eba52333/channel/488778/offerista_top_angebote_2023.csv',
                'campaign_title' => 'Dein Garten kann mehr',
                'campaign_pdf' => 'OMC-23-April.pdf',
                'brochure_number_prefix' => 'Discover_April_2023',
                'start_date' => '01.04.2023',
                'end_date' => '30.04.2023',
            ],

        ];

        # services
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sFtp->connect('74');
        $sFtp->changedir('Discover');

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
            $aData = $sPss->readFile($localArticleFile, TRUE, ',')->getElement(0)->getData();

            # build a list with categories and give them page numbers
            $aCategories = [];
            $pageIndex = 0;

            foreach ($aData as $singleRow) {
                if (!empty($singleRow['wunschkategorie'])) {
                    var_dump($singleRow['wunschkategorie']);
                    $category = $singleRow['wunschkategorie'];
                } else {
                    $category = explode(' > ', $singleRow['product_type'])[1];
                }

                if (!isset($aCategories[$category]) && empty($singleRow['wunschkategorie'])) {

                    $pageIndex++;
                    $aCategories[$category] = $pageIndex;
                } elseif (!isset($aCategories[$category]) && !empty($singleRow['wunschkategorie'])) {
                    $aCategories[$category] = (int)$singleRow['wunschreihenfolge'];
                }
            }

            $aNewGen = [];
            foreach ($aData as $singleRow) {

                /*
                 * We skip products from this category
                 * https://redmine.offerista.com/issues/77019
                 */
                if ($singleRow['wunschkategorie'] == 'Klima und Insektenschutz') {
                    continue;
                }

                if (!empty($singleRow['wunschkategorie'])) {
                    $category = $singleRow['wunschkategorie'];
                    $singleRow['Page'] = $aCategories[$singleRow['wunschkategorie']];
                } else {
                    $category = explode(' > ', $singleRow['product_type'])[1];
                }

                # check for missing article IDs
                if (!$aArticleIds['DISCOVER_' . $singleRow['id']]) {
                    $this->_logger->warn('fehlende Artikelnummer ' . 'DISCOVER_' . $singleRow['id']);
                    continue;
                }

                # build the discover array
                if (!empty($singleRow['wunschkategorie'])) {
                    $priority = rand(1, 3);
                    $aNewGen[$singleRow['Page']]['page_metaphore'] = $category;
                    $aNewGen[$singleRow['Page']]['products'][] = [
                        'product_id' => $aArticleIds['DISCOVER_' . $singleRow['id']],
                        'priority' => $priority,
                    ];
                } else {

                    $priority = rand(1, 3);
                    $aNewGen[$aCategories[$category]]['page_metaphore'] = $category;
                    $aNewGen[$aCategories[$category]]['products'][] = [
                        'product_id' => $aArticleIds['DISCOVER_' . $singleRow['id']],
                        'priority' => $priority,
                    ];
                }
            }
            ksort($aNewGen);
            # the service in the new framework
            $response = Blender::blendApi($companyId, $aNewGen, $campaign['brochure_number_prefix'] . '_' . str_replace(['header-', '.pdf'], '', $campaign['campaign_pdf']));


            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                throw new Exception('blender api did not work out');
            }

            $strLayout = $response['body'];
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('OBI: ' . $campaign['campaign_title'])
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
