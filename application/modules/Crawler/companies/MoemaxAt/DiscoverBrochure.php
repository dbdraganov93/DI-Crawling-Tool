<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Moemax AT (ID:72787)
 */
class Crawler_Company_MoemaxAt_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # upload products_category_mapping and cover_page.pdf                   #
        # onto our ftp server (folder 72787/Discover)                           #
        #                                                                       #
        # adjust the variables ad needed                                        #
        #########################################################################


        $categoryFile = 'Vat02-1-a.xls';
        $campaignName = 'Vat02-1-a';
        $startDate = '08.03.2021';
        $endDate = '20.03.2021';

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

#        $localFolder = $sFtp->connect($companyId, TRUE);
        $localFolder = $sFtp->connect('72787', TRUE);
        $sFtp->changedir('Discover');
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#[^\.]+\.pdf#', $singleFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }
        }

        $localCategoryFile = $sFtp->downloadFtpToDir( $categoryFile , $localFolder);
        $aApiData = $sApi->getActiveArticleCollection($companyId);

        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $aData = $sPss->readFile($localCategoryFile, TRUE)->getElement(0)->getData();

        $aCategories = [];
        $pageIndex = 0;
        foreach ($aData as $singleRow) {
            if(!isset($aCategories[$singleRow['ArtBez']])) {
                $pageIndex++;
                $aCategories[$singleRow['ArtBez']] = $pageIndex;
            }
        }

        $aNewGen = [];
        $aMissingArticles = [];
        foreach ($aData as $singleRow) {

            $singleRow['article_number'] = ltrim($singleRow['ArtNr'] . $singleRow['Af'], '0');
            if(!$aArticleIds[$singleRow['article_number']]) {
                #$this->_logger->warn('fehlende Artikelnummer ' . $singleRow['article_number']);
                $aMissingArticles[] = 'ArtNr: ' . $singleRow['ArtNr'] . ' | Af: ' . $singleRow['Af'];
                continue;
            }


            $priority = rand(1, 3);
            $aNewGen[$aCategories[$singleRow['ArtBez']]]['page_metaphore'] = $singleRow['ArtBez'];
            $aNewGen[$aCategories[$singleRow['ArtBez']]]['products'][] = [
                'product_id' => $aArticleIds[$singleRow['article_number']],
                'priority' => $priority,
            ];

        }

        Zend_Debug::dump($aMissingArticles);
        $response = Blender::blendApi('72787', $aNewGen, $campaignName);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];
        Zend_Debug::dump($strLayout);die;

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();


        $eBrochure->setTitle('Moemax: Wochenangebote')
            ->setBrochureNumber($campaignName)
            ->setUrl($localBrochurePath)
            ->setVariety('leaflet')
            ->setStart($startDate)
            ->setEnd($endDate)
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        # move the PDF file after the creation of the Discover
        #$sFtp->move('./' . $pdfFile, './archive/' . $pdfFile);
        #$sFtp->close();

        return $this->getResponse($cBrochures, $companyId);
    }

}