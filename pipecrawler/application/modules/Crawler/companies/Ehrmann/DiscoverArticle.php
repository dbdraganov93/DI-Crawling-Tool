<?php
/*** Discover Crawler für Ehrmann (ID: 71752)*/

class Crawler_Company_Ehrmann_DiscoverArticle extends Crawler_Generic_Company
{

    private string $spreadSheetId = '1g1SaKeToH1EeCu14CfUGNljjtppUNyTO3LY93oYRGOs';
    private string $sheetName = 'run_23/11-02/12';
    private string $startDate = '23.11.2022';
    private string  $endDate = '02.12.2022';

    public function crawl($companyId)
    {

        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aData = $sGS->getFormattedInfos( $this->spreadSheetId, 'A1', 'Q', $this->sheetName);
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
                ->setStart($this->startDate)
                ->setEnd($this->endDate)
                ->setVisibleStart($eArticle->getStart());

            $cArticles->addElement($eArticle, true, 'complex', false);
        }
        return $this->getResponse($cArticles);
    }
}
