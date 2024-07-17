<?php

/**
 * Discover Crawler für toom (ID: 123)
 */

class Crawler_Company_toom_DiscoverArticle extends Crawler_Generic_Company
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

        $articleFile = 'Discover Product Data Set toom OH-25.07.xlsx';
        $startDate = '22.07.2021';
        $endDate = '25.07.2021';

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();


        $localFolder = $sFtp->connect($companyId, TRUE);
        $localArticleFile = $sFtp->downloadFtpToDir('./Discover/' . $articleFile , $localFolder);

        $aData = $sPss->readFile($localArticleFile, TRUE)->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($aData as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber('DISCOVER_' . $singleRow['article_number'])
                ->setTitle($singleRow['title'])
                ->setText($singleRow['text'])
                ->setPrice($singleRow['price'])
                ->setSuggestedRetailPrice($singleRow['suggested_retail_price'])
                ->setArticleNumberManufacturer($singleRow['article_number_manufacturer'])
                ->setImage($singleRow['image'])
                ->setUrl($singleRow['url with utm tags'])
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setVisibleStart($startDate)
                ->setVisibleEnd($endDate);

            $cArticles->addElement($eArticle,TRUE, 'complex', FALSE);

        }

        return $this->getResponse($cArticles, $companyId);
    }
}