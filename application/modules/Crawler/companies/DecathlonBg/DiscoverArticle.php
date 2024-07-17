<?php

/**
 * Discover article crawler for Decathlon Bulgaria (ID: 82612)
 */

class Crawler_Company_DecathlonBg_DiscoverArticle extends Crawler_Generic_Company
{

    /**
     * @var mixed
     */
    private array $campaignData;

    public function crawl($companyId) // refactor it similar to IKEA for multiple row reading
    {
        $this->companyId = $companyId;
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $this->campaignData = $sGSRead->getCustomerData('DecathlonBg');

        $articleInfos = $sGSRead->getFormattedInfos($this->campaignData['spreadsheetId'], 'A1', 'W');

        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($articleInfos as $singleArticle) {
            $article = $this->createArticle($singleArticle);

            $cArticles->addElement($article);
        }

        return $this->getResponse($cArticles);
    }

    private function createArticle(array $singleArticle): Marktjagd_Entity_Api_Article
    {
        $eArticle = new Marktjagd_Entity_Api_Article();
        foreach ($singleArticle as $key => $value) {
            if (!property_exists($eArticle, $key)) {
                continue;
            }
            $setterName = 'set' . ucwords($key);
            if (method_exists($eArticle, $setterName)) {
                $this->_logger->info($this->companyId . ': ' . $setterName . ' used.');
                $eArticle->{$setterName}($value);
            }
        }
        $eArticle->setArticleNumber($eArticle->getArticleNumber() . '_Disc_' . date('W_Y', strtotime($this->campaignData['start'])));

        return $eArticle;
    }
}
