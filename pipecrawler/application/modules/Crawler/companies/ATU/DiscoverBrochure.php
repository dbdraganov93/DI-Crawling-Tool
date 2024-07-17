<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover brochure crawler for ATU (ID: 83)
 */
class Crawler_Company_ATU_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#feed\.csv$#', $singleRemoteFile)) {
                $localCategoryFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            } elseif (preg_match('#\.pdf$#', $singleRemoteFile)) {
                $localCoverFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($localCategoryFile, TRUE, ';')->getElement(0)->getData();

        $aDiscoverData = [];
        foreach ($aData as $singleRow) {
            $aDiscoverData[$singleRow['category']][] = $sApi->findArticleByArticleNumber($companyId, $singleRow['article_number'] . '_Disc')['id'];
        }

        $strLayout = $this->createLayout($aDiscoverData);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('A.T.U.: WINTERPAKET ZUM TOP-PREIS')
            ->setBrochureNumber('ATU_12_2023')
            ->setUrl($localCoverFile)
            ->setStart('20.12.2023')
            ->setEnd('31.12.2023')
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures);
    }

    private function createLayout(array $articles): string
    {
        $this->_logger->info("preparing Blender request");
        $discover = [];
        foreach ($articles as $category => $articleIds) {
            $products = [];
            foreach ($articleIds as $singleArticleId) {
                $products[] = [
                    'product_id' => $singleArticleId,
                    'priority' => rand(1, 3)
                ];
            }

            $discover[] = [
                'page_metaphor' => $category,
                'products' => $products
            ];
        }

        $this->_logger->info("Requesting Discover layout");
        $response = Blender::blendApi('83', $discover, 'A.T.U. 2023');

        if (200 != $response['http_code']) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        return $response['body'];
    }
}