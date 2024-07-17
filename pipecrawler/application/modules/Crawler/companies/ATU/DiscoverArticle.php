<?php

/**
 * Discover article crawler for ATU (ID: 83)
 */

class Crawler_Company_ATU_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#feed\.csv$#', $singleRemoteFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }
        $aData = $sPss->readFile($localArticleFile, TRUE, ';')->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($singleRow['article_number'] . '_Disc')
                ->setTitle(trim($singleRow['title']))
                ->setText($singleRow['Text'])
                ->setPrice($singleRow['price'])
                ->setImage($singleRow['image'])
                ->setUrl($singleRow['url'])
                ->setStart('20.12.2023')
                ->setEnd('31.12.2023')
                ->setVisibleStart($eArticle->getStart());

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles);
    }
}