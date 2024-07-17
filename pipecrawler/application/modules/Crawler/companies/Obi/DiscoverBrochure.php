<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Obi (ID: 74)
 */
class Crawler_Company_Obi_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.pdf$#', $singleRemoteFile)) {
                $localBrochureFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
            if (preg_match('#\.csv$#', $singleRemoteFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        $sFtp->close();

        $cArticles = $sApi->getActiveArticleCollection(78643);
        $aArticleIds = [];

        foreach ($cArticles->getElements() as $singleArticle) {
            $aArticleIds[$singleArticle->getArticleNumber()] = $singleArticle->getArticleId();
        }

        $aDiscover = [];
        $aData = $sPss->readFile($localArticleFile, TRUE)->getElement(0)->getData();
        foreach ($aData as $singleRow) {
            $aDiscover[$singleRow['product_type']][] = $aArticleIds[$singleRow['id']];
        }
        $discover = [];
        foreach (array_keys($aDiscover) as $category) {
            $products = [];
            foreach ($aDiscover[$category] as $articleIds) {
                $products[] = [
                    'product_id' => $articleIds,
                    'priority' => rand(1, 3)
                ];
            }

            if (empty($products)) {
                $this->_logger->info("SKIPPING CATEGORY: {$category} no products found");
                continue;
            }

            $discover[] = [
                'page_metaphor' => $category,
                'products' => $products
            ];
        }
        $response = Blender::blendApi('78643', $discover, 'discover_stage', 'stage');

        if (200 != $response['http_code']) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }
        Zend_Debug::dump($response['body']);
        die;
    }
}
