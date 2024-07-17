<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Article crawler for Aldi Süd (ID: 29)
 */

class Crawler_Company_Aldi_DiscoverBrochureSued extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $week = $sTimes->getWeekNr();
        $date = $week;


        # look for an articles fileand header psd on the FTP server
        $localPath = $sFtp->connect($companyId, TRUE);
        $sFtp->changedir('Discover');

        foreach ($sFtp->listFiles() as $singleFtpFile) {
            if (preg_match("#.xlsx$#i", $singleFtpFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFtpFile, $localPath);
            }
            if (preg_match("#.pdf$#i", $singleFtpFile)) {
                $localPdfFile = $sFtp->downloadFtpToDir($singleFtpFile, $localPath);
            }
        }
        $sFtp->close();

        if(!($localArticleFile && $localPdfFile)) {
            $this->_logger->warn('no articles or header pdf could be found');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::FAILED);
            return $this->_response;
        }


        $aData = $sPss->readFile($localArticleFile, TRUE)->getElement(0)->getData();
        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getUrl()] = $eApiData->getArticleId();
        }

        $pages = ['Aktionshighlights'];
        $aNewGen = [];
        foreach ($aData as $rowNr => $singleRow) {

            if (empty($singleRow['Site']) || !preg_match('#http#', $singleRow['Site']))
                continue;

            if(!$aArticleIds[$singleRow['Site']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . $singleRow['Site']);
                continue;
            }

            #if(!in_array($singleRow['carthegory'], $pages)) {
            #    $pages[] = $singleRow['carthegory'];
            #}


            $singleRow['Page'] = array_search($singleRow['carthegory'],$pages, TRUE);

            $aNewGen[$singleRow['Page']]['products'][] = [
                'priority' => rand(2, 3),
                'product_id' => $aArticleIds[$singleRow['Site']]
            ];
            $aNewGen[$singleRow['Page']]['page_metaphore'] = 'Aktionshighlights';

            $date = $singleRow['WT'];

        }

        $response = Blender::blendApi($companyId, $aNewGen);
        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle("Aldi: sichere dir unsere Aktionshighlights")
            ->setBrochureNumber("Discover_" . $date)
            ->setUrl($localPdfFile)
            ->setVariety('leaflet')
            ->setStart($date)
            ->setEnd($date." 23:59:59")
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        # move the PDF file after the creation of the Discover
        #$sFtp->move('./' . $pdfFile, './archive/' . $pdfFile);
        $sFtp->close();

        return $this->getResponse($cBrochures, $companyId);
    }
}
