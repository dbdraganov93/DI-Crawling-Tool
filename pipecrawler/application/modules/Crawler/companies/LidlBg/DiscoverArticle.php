<?php

/**
 * Discover Crawler für Lidl BG (ID: 80669 )
 */

class Crawler_Company_LidlBg_DiscoverArticle extends Crawler_Generic_Company
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

        $spreadSheetId = '1FHRpQD2HoElhRhCHsgFRVlsKxRfIty6NHoPbWWq4AU4';
        $sheetName = 'Discover Product List Example';
        $startDate = '14.03.2022';
        $endDate = '10.04.2022';

        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aData = $sGS->getFormattedInfos($spreadSheetId, 'A1', 'Q', $sheetName);
        $this->_logger->info('Found ' . count($aData) . ' rows.');

        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($aData as $singleRow) {
            $this->_logger->info('Adding: ' . 'DISCOVER_' . $singleRow['article_number']);
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber('DISCOVER_' . $singleRow['article_number'])
                ->setTitle($singleRow['title'])
                ->setText($singleRow['text'])
                ->setSuggestedRetailPrice(str_replace(',', '.', $singleRow['suggested_retail_price']))
                ->setPrice($singleRow['price'])
                ->setImage($singleRow['image'])
                ->setUrl($singleRow['url'])
                ->setNational(1)
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setVisibleStart($eArticle->getStart());

            #this block represents the Unit per Price logic as follows:
            #unitPrice: {unit: "300 g/опаковка", value: "3.19"}
            #where the value is the price
            if (isset($singleRow['unit']) && strlen($singleRow['unit'])) {
                $additionalProperties['unitPrice'] = ['unit' => str_replace(',', '.', $singleRow['unit']) ,'value' => str_replace(',', '.', $singleRow['price'])];
                $eArticle->setAdditionalProperties(json_encode($additionalProperties));
            }
            $cArticles->addElement($eArticle, true, 'complex', false);
        }
        return $this->getResponse($cArticles);
    }
}

