<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für IKEA RO (Stage ID: 77590)
 */
class Crawler_Company_IkeaRo_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        #                                                                       #
        # upload the Excel sheet to GDrive                                      #
        #                                                                       #
        # adjust spreadsheetId, sheetName, start and end date                   #
        #########################################################################

        $spreadSheetId = '1C7vXF0iiu-zWRSqvD2TM9_A9kBCdLjfTeke88kJE2QI';
        $sheetName = 'Discover Product Data - RO';
        $startDate = '20.10.2021';
        $endDate = '31.12.2022';
        $brochureNumber = 'IKEA_RO_Discover_2022';
        $coverPage = 'IKEA_CMIBG_Discover_1400x2000.pdf';

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

            if(!$aArticleIds['DISCOVER_' . $singleRow['article_number']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . $singleRow['article_number']);
            }
        }

        ksort($aNewGen);
        $response = Blender::blendApi($companyId, $aNewGen, $brochureNumber);
        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->connect('80534', TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#'.$coverPage.'#', $singleFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('ИКЕА - Discover')
            ->setBrochureNumber($brochureNumber)
            ->setUrl($localBrochurePath)
            ->setVariety('leaflet')
            ->setStart($startDate)
            ->setEnd($endDate)
            ->setVisibleStart($eBrochure->getStart())
            ->setNational(1)
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        # move the PDF file after the creation of the Discover
//        $sFtp->move('./' . $pdfFile, './archive/' . $pdfFile);
        $sFtp->close();

        return $this->getResponse($cBrochures);
    }
}
