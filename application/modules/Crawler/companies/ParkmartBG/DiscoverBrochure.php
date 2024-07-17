<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';
/**
 * Discover Crawler for Parkmart BG (ID: 81412)
 */

class Crawler_Company_ParkmartBG_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        $campaigns = [
            0 => [
                'url' => '1Kg0bqiGFWfnIH5Cvu3IEX0Ukp8y-xMERxhsmUIP3vpU',
                'name' => 'Discover Product List Example',
                'cover_page' => 'Header_Parkmart.pdf',
                'city'=> 'Варна',
                'title' => 'Varna',
                'start' => '15.12.2021',
                'brochure_number'=> 'DC_1_var',
                'end' => '31.12.2021 23:59:00'
            ],
            1 => [
                'url' => '1Jff-JtsOiGn60tCFhAR0GOYOX67HMTR5tCeow1VraUE',
                'name' => 'Discover Product List Example',
                'cover_page' => 'Header_Parkmart.pdf',
                'city'=> 'София',
                'title' => 'Sofia',
                'start' => '15.12.2021',
                'brochure_number'=> 'DC_2_sof',
                'end' => '31.12.2021 23:59:00'
            ],
            2 => [
                'url' => '1TDPchZR3aE9c5dqNNlLh5cjgJVydn7zskTVit_rIZMA',
                'name' => 'Discover Product List Example',
                'city'=> 'Бургас',
                'title' => 'Burgas',
                'cover_page' => 'Header_Parkmart.pdf',
                'start' => '15.12.2021',
                'brochure_number'=> 'DC_3_bur',
                'end' => '31.12.2021 23:59:00'
            ]
        ];

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sFtp->connect($companyId, TRUE);

        foreach($campaigns as $campaign) {


            $localBrochurePath = $sFtp->downloadFtpToCompanyDir("./{$campaign['cover_page']}" , $companyId);
            $aApiData = $sApi->getActiveArticleCollection($companyId);

            $aArticleIds = [];
            foreach ($aApiData->getElements() as $eApiData) {
                $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
            }

        $aData = $sGS->getFormattedInfos($campaign['url'], 'A1', 'P' ,'Discover Product List Example');

            $aNewGen = [];
            foreach ($aData as $singleRow) {

                if(!$aArticleIds[$campaign['brochure_number'] . '_' . $singleRow['article_number']]) {
                    $this->_logger->warn('missing article number ' . $campaign['brochure_number'] . '_' . $singleRow['article_number']);
                    continue;
                }

                $aNewGen[$singleRow['page']]['page_metaphore'] = $singleRow['category'];
                 $aNewGen[$singleRow['page']]['products'][] = [
                    'product_id' => $aArticleIds[$campaign['brochure_number'] . '_' . $singleRow['article_number']],
                    'priority' => rand(1,3),
                ];

            }
           ksort($aNewGen);

            $response = Blender::blendApi($companyId, $aNewGen, $campaign['brochure_number']);

            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                throw new Exception('blender api did not work out');
            }

            $stores = $sApi->findAllStoresForCompany($companyId);
            $storenumber = [];
            foreach($stores as $store)
            {
                if($store['city'] == $campaign['city'])
                {
                    $storenumber[]= $store['number'];
                }

            }

            $strLayout = $response['body'];
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Parkmart: ' . $campaign['title'])
                ->setBrochureNumber($campaign['brochure_number'])
                ->setUrl($localBrochurePath)
                ->setVariety('leaflet')
                ->setStart($campaign['start'])
                ->setStoreNumber(implode(' , ' , $storenumber))
                ->setEnd($campaign['end'])
                ->setVisibleStart($eBrochure->getStart())
                ->setTrackingBug($campaign['tracking'])
                ->setLayout($strLayout);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

}
