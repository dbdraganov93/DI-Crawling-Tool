<?php

/**
 * Discover Crawler for AVON RO (ID: 80414 )
 */

class Crawler_Company_AvonRo_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # Update the article data in the google sheet and modify the variables  #
        #                                                                       #
        # adjust articleFile                                                    #
        #########################################################################

        $gSheetId = '19z60J7X6UZ6xIxNc8zuS3c3CZKPSLpyBY2nqm53Ou3U';
        $visibleStart = '06.12.2021';
        $start = '06.12.2021';
        $end = '31.12.2021 23:59:59';


        $sGSheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $articleList = $sGSheet->getFormattedInfos($gSheetId, 'A2', 'N', 'Discover Data');

        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($articleList as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber('D_' . $singleRow['article_number'])
                ->setTitle($singleRow['title'])
                ->setText($singleRow['text'])
                ->setPrice(str_replace('.', ',', $singleRow['price']))
                ->setSuggestedRetailPrice(str_replace('.', ',', $singleRow['suggested_retail_price']))
                ->setImage($singleRow['image'])
                ->setUrl($singleRow['url'])
                ->setNational('1')
                ->setStart($start)
                ->setEnd($end)
                ->setVisibleStart($visibleStart);

            $cArticles->addElement($eArticle,TRUE, 'complex', FALSE);

        }

        return $this->getResponse($cArticles, $companyId);
    }
}