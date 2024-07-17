<?php

/**
 * Article crawler for Baur (ID: 82357)
 */

class Crawler_Company_Baur_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        $articleFile = 'baurdatenfeeds_offeristade_reworked.csv';
        $startDate = '01.07.2022';
        $endDate = '05.08.2022';

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $cArticles = new Marktjagd_Collection_Api_Article();


        $localFolder = $sFtp->connect($companyId, true);

        $localArticleFile = $sFtp->downloadFtpToDir('./' . $articleFile, $localFolder);

        $aData = $sPss->readFile($localArticleFile, true)->getElement(0)->getData();

        $sFtp->close();

        foreach ($aData as $singleRow) {

            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber('DISCOVER_' . $singleRow['article_number'])
                ->setTitle($singleRow['title'])
                ->setText($singleRow['text'])
                ->setImage($singleRow['image'])
                ->setPrice($singleRow['price'])
                ->setUrl($singleRow['url'])
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setSuggestedRetailPrice($singleRow['suggested_retail_price']);

            $cArticles->addElement($eArticle);

        }
        return $this->getResponse($cArticles, $companyId);
    }



}
