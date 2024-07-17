<?php

/**
 * Discover Crawler für IKEA HU (Stage ID: 77987)
 */

class Crawler_Company_IkeaHu_DiscoverArticle extends Crawler_Generic_Company
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

        $spreadSheetId = '1C7vXF0iiu-zWRSqvD2TM9_A9kBCdLjfTeke88kJE2QI';
        $sheetName = 'Discover Product Data - HU';
        $startDate = '20.10.2021';
        $endDate = '31.12.2022';

        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aData = $sGS->getFormattedInfos($spreadSheetId, 'A1', 'P', $sheetName);

        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($aData as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber('DISCOVER_' . $singleRow['article_number'])
                ->setTitle($singleRow['title'])
                ->setText($singleRow['text'])
                ->setPrice($singleRow['suggested_retail_price'])
                ->setImage($singleRow['image'])
                ->setUrl($singleRow['url'])
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setVisibleStart($eArticle->getStart());

            $cArticles->addElement($eArticle);
        }
        return $this->getResponse($cArticles);
    }
}
