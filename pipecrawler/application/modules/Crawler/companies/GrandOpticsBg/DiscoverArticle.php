<?php

/**
 * Discover Crawler für Grand optics BG (ID: 80575)
 */

class Crawler_Company_GrandOpticsBg_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # upload the Excel sheet to GDrive                                      #
        #                                                                       #
        # adjust spreadsheetId, sheetName, start and end date                   #
        #########################################################################

        $spreadSheetId = '1F5iMtcvuAdUfHgD5f3gCvd79MQD1Kms7-w4MBNY5mkg';
        $sheetName = 'Discover Product List';
        $startDate = '08.10.2021';
        $endDate = '24.10.2021';

        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aData = $sGS->getFormattedInfos($spreadSheetId, 'A1', 'P', $sheetName);

        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($aData as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber('DISCOVER_' . $singleRow['article_number'])
                ->setTitle($singleRow['title'])
                ->setText($singleRow['text'])
                ->setSuggestedRetailPrice($singleRow['suggested_retail_price'])
                ->setPrice($singleRow['price'])
                ->setImage($singleRow['image'])
                ->setUrl($singleRow['url'])
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setNational(1)
                ->setVisibleStart($eArticle->getStart());

            $cArticles->addElement($eArticle);
        }
        return $this->getResponse($cArticles);
    }
}
