<?php

require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover Crawler für Möbelland (ID: 73716)
 */
class Crawler_Company_Moebelland_Discover extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $campaignParameters = [
            'campaignStart' => '09.06.2021',
            'campaignEnd' => '21.06.2021',
        ];

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $localPath = $sFtp->connect($companyId, TRUE);

        $localArticleFile = '';
        $brochureFile = '';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.pdf$#', $singleFile)) {
                $brochureFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }
            if (preg_match('#\.xlsx?$#', $singleFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }

            if (strlen($brochureFile)
                && strlen($localArticleFile)) {
                break;
            }
        }
        $sFtp->close();

        if (!strlen($brochureFile)) {
            throw new Exception($companyId . ': no brochure file found.');
        }

        if (!strlen($localArticleFile)) {
            throw new Exception($companyId . ': no article file found.');
        }

        $aData = $sPss->readFile($localArticleFile, TRUE)->getElement(1)->getData();

        $category = [];
        $brochureNumber = '';
        foreach ($aData as $singleRow) {
            if (is_null($singleRow['article_number'])) {
                break;
            }
            if (!strlen($brochureNumber)) {
                $brochureNumber = $singleRow['brochure_number'];
            }

            if (!strlen($singleRow['category'])) {
                $singleRow['category'] = count($category);
            }

            $category[$singleRow['category']][] = [
                'product_id' => $aArticleIds[trim($singleRow['article_number'])],
                'priority' => $singleRow['layout_priority']
            ];
        }

        $discover = [];
        foreach ($category as $categoryName => $products) {
            $discover[] = [
                'page_metaphore' => $categoryName,
                'products' => $products
            ];
        }
        $this->_logger->info("requesting Discover layout");
        $response = Blender::blendApi($companyId, $discover, $brochureNumber);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Hier find ich\'s gut')
            ->setBrochureNumber($brochureNumber)
            ->setUrl($brochureFile)
            ->setVariety('leaflet')
            ->setStart($campaignParameters['campaignStart'])
            ->setEnd($campaignParameters['campaignEnd'])
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures);
    }
}