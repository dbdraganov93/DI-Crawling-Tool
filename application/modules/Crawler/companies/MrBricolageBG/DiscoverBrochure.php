<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover Crawler for MrBricolage BG (ID: 80637 )
 */
class Crawler_Company_MrBricolageBg_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # upload the cover_pages onto our ftp server (folder 80637)             #
        #                                                                       #
        # adjust the variables as needed                                        #
        #########################################################################

        $campaigns = [

            0 => [
                'url' => '1LGeB7OnvBzuxxVZHRsu1YNVoVxjWXudCorJ0EM1vpFk',
                'name' => 'Бриколаж брошура до 16.03.2022',
                'cover_page' => 'Header_MrBricolageBG_16_03.pdf',
                'brochure_number' => 'DC_BricBG_2',
                'start' => '03.03.2022',
                'end' => '16.03.2022 23:59:00',
            ],

        ];


        # services
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sFtp->connect($companyId, TRUE);


        foreach ($campaigns as $campaign) {
            //get local ftp folder path
            $localBrochurePath = $sFtp->downloadFtpToCompanyDir("./{$campaign['cover_page']}", $companyId);

            //get article collection for the given company ID
            $aApiData = $sApi->getActiveArticleCollection($companyId);
            $aArticleIds = [];
            foreach ($aApiData->getElements() as $eApiData) {
                $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
            }

            // get the resource feed and read it
            $aData = $sGS->getFormattedInfos($campaign['url'], 'A1', 'P', 'Discover Product List Bricolage');

            //variable expecting feed result with additional adjustments as per client's demand
            $aNewGen = [];
            foreach ($aData as $singleRow) {


                if (!$aArticleIds[$campaign['brochure_number'] . '_' . $singleRow['article_number']]) {
                    $this->_logger->warn('fehlende Artikelnummer ' . $singleRow['article_number']);
                }
                $aNewGen[$singleRow['page']]['page_metaphore'] = $singleRow['category'];
                $aNewGen[$singleRow['page']]['products'][] = [

                    'priority' => rand(1, 3),
                    'product_id' => $aArticleIds[$campaign['brochure_number'] . '_' . $singleRow['article_number']]
                ];

            }

            ksort($aNewGen);

            $response = Blender::blendApi($companyId, $aNewGen, $campaign['brochure_number']);


            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                throw new Exception('blender api did not work out');
            }


            $strLayout = $response['body'];

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('MrBricolage: ' . $campaign['name'])
                ->setBrochureNumber($campaign['brochure_number'])
                ->setUrl($localBrochurePath)
                ->setVariety('leaflet')
                ->setStart($campaign['start'])
                ->setEnd($campaign['end'])
                ->setVisibleStart($eBrochure->getStart())
                ->setTrackingBug($campaign['tracking'])
                ->setLayout($strLayout);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}