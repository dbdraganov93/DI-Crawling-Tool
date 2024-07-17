<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover brochure crawler for Gravis (ID: 29034)
 */
class Crawler_Company_Gravis_DiscoverBrochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sGsRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $aSpreadsheetInfos = $sGsRead->getCustomerData('GravisGer');

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles('./discover') as $singleRemoteFile) {
            if (preg_match('#' . $aSpreadsheetInfos['brochureName'] . '#', $singleRemoteFile)) {
                $localBrochureFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);

            } elseif (preg_match('#' . $aSpreadsheetInfos['articleFileName'] . '#', $singleRemoteFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($localArticleFile, TRUE)->getElement(0)->getData();
        $aArticleIds = [];
        $aPrioArticleIds = [];
        foreach ($aData as $singleRow) {
            if (!preg_match('#\d+#', $singleRow['articleNumber'])) {
                continue;
            }
            $id = $sApi->findArticleByArticleNumber($companyId, $singleRow['articleNumber'] . '_' . $aSpreadsheetInfos['brochureNumber'])['id'];
            $aArticleIds[$singleRow['category']][$singleRow['articleNumber']] = $id;
            if (preg_match('#3#', $singleRow['priority'])) {
                $aPrioArticleIds[] = $id;
            }
        }

        $aDiscover = [];
        $pageNumber = 0;
        foreach ($aArticleIds as $categoryName => $articles) {
            $pageNumber++;
            $aDiscover[$pageNumber]['page_metaphor'] = $categoryName;

            foreach ($articles as $articleId) {
                if (!$articleId) {
                    continue;
                }

                $aDiscover[$pageNumber]['products'][] = [
                    'priority' => in_array($articleId, $aPrioArticleIds) ? 1 : rand(2, 3),
                    'product_id' => $articleId
                ];
            }
        }
        ksort($aDiscover);

        $response = Blender::blendApi($companyId, $aDiscover, $aSpreadsheetInfos['brochureNumber']);

        if (200 != $response['http_code']) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];
        $jLayout = json_decode($strLayout, TRUE);

        $jLayout[3] = $this->reArrangeLayout($jLayout[3], $aPrioArticleIds);
        $jLayout[4] = $this->reArrangeLayout($jLayout[4], $aPrioArticleIds);

        $strLayout = json_encode($jLayout);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Elektronikangebote')
            ->setBrochureNumber($aSpreadsheetInfos['brochureNumber'])
            ->setUrl($localBrochureFile)
            ->setVariety('leaflet')
            ->setStart($aSpreadsheetInfos['validityStart'])
            ->setVisibleStart($eBrochure->getStart())
            ->setEnd($aSpreadsheetInfos['validityEnd'])
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures);
    }

    /**
     * @param $jLayout
     * @param $banner
     * @param $singlePage
     * @param array $aPrioArticleIds
     * @param $highlightProduct
     * @param array $amountMatch
     * @return array
     */
    public function reArrangeLayout($aInfos, $aPrioArticleIds): array
    {
        foreach ($aInfos['pages'] as $pageNo => &$singlePage) {
            for ($i = 0; $i < count($singlePage['modules']); $i++) {
                if (preg_match('#banner_1#', $singlePage['modules'][$i]['name'])
                    && preg_match('#top#', $singlePage['modules'][$i]['position'])
                    && $pageNo == $singlePage['modules'][$i]['page'] - 1) {
                    $banner = $singlePage['modules'][$i];
                    continue;
                }
                if (!isset($singlePage['modules'][$i]['products'])) {
                    continue;
                }
                if (preg_match('#product_1_1#', $singlePage['modules'][$i]['name'])
                    && in_array($singlePage['modules'][$i]['products'][0]['id'], $aPrioArticleIds)) {
                    continue;
                }

                for ($j = 0; $j < count($singlePage['modules'][$i]['products']); $j++) {
                    if (in_array($singlePage['modules'][$i]['products'][$j]['id'], $aPrioArticleIds)) {
                        $highlightProduct = $singlePage['modules'][$i]['products'][$j];
                        $highlightProduct['priority'] = 1;
                        if (preg_match('#product_(\d)_1#', $singlePage['modules'][$i]['name'], $amountMatch)
                            && $amountMatch[1] > 1) {
                            $singlePage['modules'][$i]['name'] = preg_replace('#product_(\d)_1#', 'product_' . ($amountMatch[1] - 1) . '_1', $singlePage['modules'][$i]['name']);
                        }
                        unset($singlePage['modules'][$i]['products'][$j]);
                        array_unshift($singlePage['modules'], [
                            'name' => 'product_1_1',
                            'products' => [$highlightProduct]
                        ]);
                        if ($pageNo == $banner['page'] - 1 && preg_match('#top#', $banner['position'])) {
                            array_unshift($singlePage['modules'], $banner);
                        }
                    }
                }
            }
            $singlePage = $this->reArrangeProductArray($singlePage);
            $singlePage = $this->reArrangeModuleArray($singlePage);
        }

        return $aInfos;
    }

    /**
     * @param $singlePage
     * @return array
     */
    public function reArrangeProductArray($singlePage): array
    {
        for ($i = 0; $i < count($singlePage['modules']); $i++) {
            if (preg_match('#banner_1#', $singlePage['modules'][$i]['name'])
                && preg_match('#top#', $singlePage['modules'][$i]['position'])
                && $i > 0) {
                unset($singlePage['modules'][$i]);
            }
        }
        for ($i = 0; $i < count($singlePage['modules']); $i++) {
            if (isset($singlePage['modules'][$i]['products'])) {
                $singlePage['modules'][$i]['products'] = array_values($singlePage['modules'][$i]['products']);
            }
        }
        return $singlePage;
    }

    public function reArrangeModuleArray($singlePage): array
    {
        $singlePage['modules'] = array_values($singlePage['modules']);

        return $singlePage;
    }
}
