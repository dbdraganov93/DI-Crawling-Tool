<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Article crawler for BabyOne (ID: 28698)
 */

class Crawler_Company_BabyOne_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $utmParameter = '?utm_source=offerista&utm_medium=discover&utm_campaign=testsieger_2022';
        $startDate = '30.05.2022';
        $endDate = '05.06.2022';
        $brochureTitle = 'BabyOne: Testsieger';
        $brochureNumber = 'Discover:Testsieger';
        $brochureName = 'autositz_testsieger-eprospekt_titelbild_a4-sas.pdf';

        $localPath = $sFtp->connect('28698', TRUE);
        $sFtp->changedir('Flyer DE');

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
            throw new Exception('No artikel.*..xlsx file was found the in -Flyer DE- directory');
        }
        if(empty($localBrochurePath)) {
            throw new Exception('No discover.*.pdf file was found the in -Flyer DE- directory');
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

            $singleRow['Page'] = array_search($singleRow['Category'],$pages, TRUE);

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
