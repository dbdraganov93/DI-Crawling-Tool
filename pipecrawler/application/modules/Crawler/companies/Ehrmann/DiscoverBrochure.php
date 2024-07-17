<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/*** Discover Crawler für Ehrmann (ID: 71752)*/
class Crawler_Company_Ehrmann_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        $spreadSheetId = '1g1SaKeToH1EeCu14CfUGNljjtppUNyTO3LY93oYRGOs';
        $brochureNumber = "KW";
        $sheetName = 'run_23/11-02/12';
        $startDate = '23.11.2022';
        $endDate = '02.12.2022';
        $title = 'Ehrmann Discover Campaign';
        $coverPage = 'TH1122_S01_new.pdf';

        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();


        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $aData = $sGS->getFormattedInfos($spreadSheetId, 'A1', 'P', $sheetName);


        $aNewGen = [];
        foreach ($aData as $singleRow) {
            $aNewGen[$singleRow['page']]['products'][] = [
                'priority' => rand(1, 3),
                'product_id' => $aArticleIds['DISCOVER_' . $singleRow['article_number']]
            ];

            $aNewGen[$singleRow['page']]['page_metaphore'] = $singleRow['category'];

            if (!$aArticleIds['DISCOVER_' . $singleRow['article_number']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . $singleRow['article_number']);
            }
        }

        ksort($aNewGen);

        $response = Blender::blendApi($companyId, $aNewGen);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }


        $strLayout = $response['body'];

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->connect('71752', TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#' . $coverPage . '#', $singleFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }

        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $date = new DateTime($startDate);
        $week = $date->format("W");

        $eBrochure->setTitle($title)
            ->setBrochureNumber('Ehrmann_' . $brochureNumber . '_' . $week)
            ->setUrl($localBrochurePath)
            ->setVariety('leaflet')
            ->setStart($startDate)
            ->setEnd($endDate)
            ->setVisibleStart($eBrochure->getStart())
            ->setDistribution("KW45")
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        # move the PDF file after the creation of the Discover if needed
//        $sFtp->move('./' . $coverPage, './archive/' . $coverPage);
//       $sFtp->close();

        return $this->getResponse($cBrochures);
    }
}
