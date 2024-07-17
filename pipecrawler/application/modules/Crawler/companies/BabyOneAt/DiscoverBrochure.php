<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Article crawler for BabyOne (ID: 73170)
 */

class Crawler_Company_BabyOneAt_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $utmParameter = '?utm_source=offerista&utm_medium=discover&utm_campaign=hellospring';
        $startDate = '28.03.2022';
        $endDate = '03.04.2022';
        $brochureTitle = 'BabyOne: Osterfreude! 🌷';
        $brochureNumber = 'Discover:Hello-Spring2';
        $brochureName = 'hellospring_ostern_eprospekt_a4_sas_at.pdf';

        $localPath = $sFtp->connect('28698', TRUE);
        $sFtp->changedir('Flyer AT');

        foreach ($sFtp->listFiles() as $singleFtpFile) {
            if (preg_match('#artikel.*.xlsx$#i', $singleFtpFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFtpFile, $localPath);
                continue;
            }
            if (preg_match('#' . $brochureName . '$#i', $singleFtpFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFtpFile, $localPath);
            }
        }

        $sFtp->close();

        if(empty($localArticleFile)) {
            throw new Exception('No artikel.*..xlsx file was found the in -Flyer AT- directory');
        }
        if(empty($localBrochurePath)) {
            throw new Exception('No discover.*.pdf file was found the in -Flyer AT- directory');
        }

        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $aData = $sPss->readFile($localArticleFile, FALSE)->getElement(0)->getData();

        $exceptionArray = [];
        $pages = [];

        $aNewGen = [];
        foreach ($aData as $rowNr => $singleRow) {

            if(empty($singleRow[1])) continue;

            if($rowNr == 0) continue;

            [$singleRow['Category'], , ,$singleRow['ID']] = explode(';', $singleRow[1]);

            if(!$aArticleIds[$singleRow['ID']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . $singleRow['ID']);
                continue;
            }
            if(!in_array($singleRow['Category'], $pages)) {
                $pages[] = $singleRow['Category'];
            }

            if (in_array($singleRow['ID'], $exceptionArray)) continue;

            $singleRow['Page'] = array_search($singleRow['Category'],$pages, true);

            $aNewGen[$singleRow['Page']]['products'][] = [
                'priority' => rand(2, 3),
                'product_id' => $aArticleIds[$singleRow['ID']]
            ];
            $aNewGen[$singleRow['Page']]['page_metaphore'] = $singleRow['Category'];
        }

        $response = Blender::blendApi($companyId, $aNewGen, $brochureNumber);
        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle($brochureTitle)
            ->setBrochureNumber($brochureNumber)
            ->setUrl($localBrochurePath)
            ->setVariety('leaflet')
            ->setStart($startDate)
            ->setEnd($endDate)
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        # move the PDF file after the creation of the Discover
        #$sFtp->move('./' . $pdfFile, './archive/' . $pdfFile);
        $sFtp->close();

        return $this->getResponse($cBrochures, $companyId);
    }

}


