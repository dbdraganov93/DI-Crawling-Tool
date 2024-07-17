<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover Crawler for Zurbrueggen (ID: 68757)
 */
class Crawler_Company_Zurbrueggen_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $campaigns = [
            0 => [
                'url' => 'https://www.semtrack.de/e?i=946e7c2fa6294b47b354eae4e00c67ba9c56412b',
                'cover_page' => 'offerista_discover_titel_3.pdf',
                'title' => 'Zurbrüggen: Angebote aus den Prospekten',
                'brochure_number' => 'Discover-Weihnachten_2',
                'start' => '14.11.2022',
                'end' => '31.12.2022 23:59:59',
                'frontpageUrl' => 'https://www.zurbrueggen.de/rabatte-und-aktionen/ausstellungsstuecke/?utm_source=offerista%20discover&utm_medium=titelbild&utm_campaign=Angebote%20aus%20den%20Prospekten'
            ],
        ];

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();


        $localPath = $sFtp->connect($companyId . '/Q5', TRUE);

        foreach ($campaigns as $campaign) {
            $localBrochurePath = $sFtp->downloadFtpToDir("./{$campaign['cover_page']}", $localPath);

            $aApiData = $sApi->getActiveArticleCollection($companyId);

            $aArticleIds = [];
            foreach ($aApiData->getElements() as $eApiData) {
                $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
            }

            $generatePath = $sFtp->generateLocalDownloadFolder($companyId);

            $localArticleFile = $sHttp->getRemoteFile($campaign['url'], $generatePath);
            $aData = $sPss->readFile($localArticleFile, TRUE, ';')->getElement(0)->getData();


            # build a list with categories and give them page numbers
            $aCategories = [];
            foreach ($aData as $singleRow) {
                if (!in_array($singleRow['section'], $aCategories) && strlen($singleRow['section'])) {
                    // Validate and skip invalid Categories (sections)
                    if (preg_match('#(\.(jpe?g|png|gif|pdf)$)|((amp|quot)$)#', $singleRow['section']) || is_float($singleRow['section'])) {
                        $this->_logger->alert(' Invalid Section -> ' . $singleRow['section'] . ' With Title: ' . $singleRow['title']);
                        continue;
                    }

                    $aCategories[] = $singleRow['section'];
                }
            }

            $aNewGen = [];
            foreach ($aData as $singleRow) {

                # check for missing article IDs
                if (!$aArticleIds['DISCOVER_' . $singleRow['id']]) {
                    $this->_logger->warn('fehlende Artikelnummer ' . 'DISCOVER_' . $singleRow['id']);
                    continue;
                }

                # build the discover array
                $priority = rand(1, 3);
                if (!empty($singleRow['section'])) {
                    $aNewGen[array_search($singleRow['section'], $aCategories) + 1]['page_metaphore'] = $singleRow['section'];
                    $aNewGen[array_search($singleRow['section'], $aCategories) + 1]['products'][] = [
                        'product_id' => $aArticleIds['DISCOVER_' . $singleRow['id']],
                        'priority' => $priority,
                    ];
                }

            }

            ksort($aNewGen);

            $response = Blender::blendApi($companyId, $aNewGen, $campaign['brochure_number']);


            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                throw new Exception('blender api did not work out');
            }

            $strLayout = $response['body'];

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Zurbrueggen: ' . $campaign['title'])
                ->setBrochureNumber($campaign['brochure_number'])
                ->setUrl($localBrochurePath)
                ->setVariety('leaflet')
                ->setStart($campaign['start'])
                ->setEnd($campaign['end'])
                ->setVisibleStart($eBrochure->getStart())
                ->setLayout($strLayout);

            $cBrochures->addElement($eBrochure);

        }

        return $this->getResponse($cBrochures, $companyId);
    }

}
