<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 *  Brochure crawler for Baur (ID: 82357)
 */
class Crawler_Company_Baur_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $articleFile = 'baurdatenfeeds_offeristade_reworked.csv';
        $startDate = '01.07.2022';
        $endDate = '05.08.2022 23:59:59';
        $cover_page = 'Offerista_Titelbild_baur_ready.pdf';
        $title = 'Jully Campaign';
        $brochure_number = 'Baur_Jully_010722';
        $tracking = 'https://m.exactag.com/ai.aspx?extProvId=100&extProvApi=baur-dis-bnk&extPu=offerista&extLi=nt_offerista_discover&extCr=titelbild';


        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        // connect to ftp server
        $localFolder = $sFtp->connect($companyId, true);

        //point to article file
        $localArticleFile = $sFtp->downloadFtpToDir('./' . $articleFile, $localFolder);

        //get cover pdf
        $cover = $sFtp->downloadFtpToCompanyDir('./' . $cover_page, $companyId);

        $aApiData = $sApi->getActiveArticleCollection($companyId);

        //get article ID' s if any uploaded in BT
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }
        //get article file and read it
        $aData = $sPss->readFile($localArticleFile, TRUE, '|')->getElement(0)->getData();

# build a list with categories and give them page numbers
        $aCategories = [];
        foreach ($aData as $singleRow) {

            if (!in_array($singleRow['category'], $aCategories) && strlen($singleRow['category'])) {
                // Validate and skip invalid Categories (sections)
                if (preg_match('#(\.(jpe?g|png|gif|pdf)$)|((amp|quot)$)#', $singleRow['category']) || is_float($singleRow['category'])) {
                    $this->_logger->alert(' Invalid Section -> ' . $singleRow['category'] . ' With Title: ' . $singleRow['title']);
                    continue;
                }
                $aCategories[] = $singleRow['category'];
            }


        }

        $aNewGen = [];
        foreach ($aData as $singleRow) {

            # check for missing article IDs
            if (!$aArticleIds['DISCOVER_' . $singleRow['article_number']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . 'DISCOVER_' . $singleRow['article_number']);
                continue;
            }

            # build the discover array
            $priority = rand(1, 3);
            if (!empty($singleRow['category'])) {
                $aNewGen[array_search($singleRow['category'], $aCategories) + 1]['page_metaphore'] = $singleRow['category'];
                $aNewGen[array_search($singleRow['category'], $aCategories) + 1]['products'][] = [
                    'product_id' => $aArticleIds['DISCOVER_' . $singleRow['article_number']],
                    'priority' => $priority,
                ];
            }

        }

        ksort($aNewGen);
       //broshure_number makes blender API call to know which brochure number with banners to take from the excel
        $response = Blender::blendApi($companyId, $aNewGen, $brochure_number);


        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];

        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Baur: ' . $title)
            ->setBrochureNumber($brochure_number)
            ->setUrl($cover)
            ->setVariety('leaflet')
            ->setStart($startDate)
            ->setEnd($endDate)
            ->setVisibleStart($eBrochure->getStart())
            ->setTrackingBug($tracking . '%%CACHEBUSTER%%')
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);

    }

}
