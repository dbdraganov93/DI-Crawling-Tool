<?php

/**
 * Article Crawler for Hellweg (ID: 28323)
 */

class Crawler_Company_Hellweg_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        ini_set('memory_limit', '4G');
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        switch ($companyId) {
            case 28323:
                $feedUrl = 'https://files.channable.com/NaLCKCtOnzaquTOQiTFogg==.csv';
                break;
            case 69602:
                $feedUrl = 'https://files.channable.com/WPTVAang-z_qt_Wpi8KfXQ==.csv';
                break;
            default:
                throw new Exception($companyId . ': no feed url specified.');
        }

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $localArticleFile = $sHttp->getRemoteFile($feedUrl, $localPath);

        $aData = $sPss->readFile($localArticleFile, TRUE, ',')->getElement(0)->getData();

        $min = count($aData) / 2;
        $max = count($aData);

        if (date('H') % 2 != 0) {
            $min = 0;
            $max = count($aData) / 2;
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        $count = 0;
        foreach ($aData as $singleRow) {
            if ($count < $min) {
                $count++;
                continue;
            } elseif ($count >= $max) {
                break;
            }
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($singleRow['id'])
                ->setTitle($singleRow['title'])
                ->setText(preg_replace('#[\x00-\x1F\x7F]#u', '', strlen($singleRow['description']) ? $singleRow['description'] . '<br/>' : '' . $singleRow['t2_kategorietitel']))
                ->setPrice($singleRow['price'])
                ->setImage($singleRow['image_link'])
                ->setUrl($singleRow['link'])
                ->setSuggestedRetailPrice($singleRow['old_price'])
                ->setManufacturer($singleRow['brand'])
                ->setEan($singleRow['ean'])
                ->setShipping($singleRow['shipping.price']);

            if ($cArticles->addElement($eArticle)) {
                $count++;
            }
        }

        return $this->getResponse($cArticles);
    }
}