<?php

/**
 * Discover Crawler für Conrad (ID: 53)
 */

class Crawler_Company_Conrad_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # Upload the article csv file onto our FTP server (folder 'Discover')   #
        #                                                                       #
        # adjust articleFile                                                    #
        #########################################################################

        $articleFile = 'Conrad Discover - products - 2021-03-25.csv';

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();


        $localFolder = $sFtp->connect($companyId, TRUE);
        $localArticleFile = $sFtp->downloadFtpToDir('./Discover/' . $articleFile , $localFolder);

        $aData = $sPss->readFile($localArticleFile, TRUE, ',')->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($aData as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber('DISCOVER_' . $singleRow['article_number'])
                ->setTitle($singleRow['title'])
                ->setText($singleRow['text'])
                ->setPrice($singleRow['price'])
                ->setArticleNumberManufacturer($singleRow['article_number_manufacturer'])
                ->setImage($singleRow['image'])
                ->setUrl($singleRow['url'])
                ->setNational($singleRow['national'])
                ->setStart($singleRow['start'])
                ->setEnd($singleRow['end'])
                ->setVisibleStart($singleRow['visible_start'])
                ->setVisibleEnd($singleRow['visible_end']);

            if ($singleRow['suggested_retail_price'] != 0) {
                $eArticle->setSuggestedRetailPrice($singleRow['suggested_retail_price']);
            }

            $cArticles->addElement($eArticle,TRUE, 'complex', FALSE);

        }

        return $this->getResponse($cArticles, $companyId);
    }
}