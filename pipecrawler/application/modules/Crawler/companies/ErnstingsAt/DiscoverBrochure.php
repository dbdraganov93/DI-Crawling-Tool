<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Ernstings Family AT (ID: 72601)
 */

class Crawler_Company_ErnstingsAt_DiscoverBrochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $categoryFile = 'Artikelliste_BKF_20220318.xlsx';
        $campaignName = 'Ernstings_Discover_2022-03-18_AT';
        $startDate = '18.03.2022';
        $endDate = '03.04.2022';
        $skipThisManyLines = 4;
        $howManyTabsCategories = 2; // 3 tabs
        $brochure = 'Offerista_Discover_BKF_220318_Titlepage AT.pdf';

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localFolder = $sFtp->connect('22133', true);
        $sFtp->changedir('Discover');
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#' . $brochure . '#', $singleFile)) {
                $this->_logger->info('Downloading PDF... please wait');
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }
            if (preg_match('#' . $categoryFile . '#', $singleFile)) {
                $this->_logger->info('Downloading Excel... please wait');
                $localCategoryFile = $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }
        }
        $sFtp->close();

        $aApiData = $sApi->getActiveArticleCollection($companyId);

        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            if (!preg_match('#DISCOVER_#', $eApiData->getArticleNumber())) {
                continue;
            }

            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $productsAndCategories = [];
        for ($i = 0; $i <= $howManyTabsCategories; $i++) {
            $mappingsTabCat = $sPss->readFile($localCategoryFile)->getElement($i)->getData();

            foreach ($mappingsTabCat as $key => $row) {
                if ($key <= $skipThisManyLines || empty($row[2])) {
                    continue;
                }

                $productsAndCategories[$row[3]][] = $row[2];
            }
        }

        $index = 1;
        $aNewGen = [];
        foreach ($productsAndCategories as $cat => $products) {
            // hardcoded fix can probably remove later on
            if ($cat == 'Babymode Online Exklusiv' || $cat == 'Umstandsmode Online Exklusiv') {
                $cat = $cat . ' nur online erhältlich';
            }

            $aNewGen[(string) $index]['page_metaphor'] = $cat;
            foreach ($products as $product) {
                if(empty($aArticleIds['DISCOVER_' . $product])) {
                    continue;
                }

                $aNewGen[(string) $index]['products'][] = [
                    'product_id' => $aArticleIds['DISCOVER_' . $product],
                    'priority' => (string) rand(1,3),
                ];
            }
            $index++;
        }

        $response = Blender::blendApi($companyId, $aNewGen, $campaignName);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Ernsting\'s family: Liebste Lieblinge')
            ->setBrochureNumber($campaignName)
            ->setUrl($localBrochurePath)
            ->setVariety('leaflet')
            ->setStart($startDate)
            ->setEnd($endDate)
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout)
            ->setTrackingBug('https://tracking.ernstings-family.at/mix/v3/?tcs=3565&rand=%%CACHEBUSTER%%&chn=O2O&src=Digitale_Prospektportale&cty=at&nt=WoGibtsWas&cmp=Prospekt&cmp_name=Mrz-BKF_Feed-Format&cmp_clu=Kinder&plmt=WoGibtsWas%27')
        ;

        $cBrochures->addElement($eBrochure);

        # move the PDF file after the creation of the Discover
        #$sFtp->move('./' . $pdfFile, './archive/' . $pdfFile);
        #$sFtp->close();

        return $this->getResponse($cBrochures, $companyId);
    }
}
