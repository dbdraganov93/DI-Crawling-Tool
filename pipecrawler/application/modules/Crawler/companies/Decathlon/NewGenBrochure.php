<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Decathlon (ID: 68079, stage: 77265)
 */
class Crawler_Company_Decathlon_NewGenBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $month = 'this';
        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#xmas_cover.pdf#', $singleFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $sFtp->close();

        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[preg_replace('#.+?NG-(\d+?)$#', '$1', $eApiData->getArticleNumber())] = $eApiData->getArticleId();
        }

        $aData = $sGS->getFormattedInfos('1v_hQ2Hx_rctlzVcn9t-fJBegvIXsIzRDmNO2R11eRhY', 'A1', 'M', 'aktuell');

        $aNewGen = [];
        foreach ($aData as $singleRow) {
            if(!$aArticleIds['Discover_' . $singleRow['REFERENZ']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . $singleRow['REFERENZ']);
                continue;
            }

            $priority = rand(2, 3);
            if (preg_match('#1#', $singleRow['Layout Priority'])) {
                $priority = 1;
            }

            $aNewGen[$singleRow['CATEGORY ORDER']]['page_metaphor'] = $singleRow['CATEGORY'];
            $aNewGen[$singleRow['CATEGORY ORDER']]['products'][] = [
                'product_id' => $aArticleIds['Discover_' . $singleRow['REFERENZ']],
                'priority' => $priority,
            ];

        }

        $response = Blender::blendApi($companyId, $aNewGen, '2021-11XMAS-NG');

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Decathlon: Weihnachten kann kommen')
            ->setBrochureNumber(date('Y', strtotime($month . ' month')) . '-' . date('m', strtotime($month . ' month')) . '-XMAS-NG')
            ->setUrl($localBrochurePath)
            ->setVariety('leaflet')
            ->setStart('25.11.2021')
            ->setEnd('24.12.2021')
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}
