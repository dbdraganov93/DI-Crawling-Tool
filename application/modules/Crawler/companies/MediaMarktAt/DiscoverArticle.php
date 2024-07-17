<?php

/**
 * Article Crawler für Media Markt AT (ID: 73214)
 */

class Crawler_Company_MediaMarktAt_DiscoverArticle extends Crawler_Generic_Company
{
    function crawl($companyId)
    {
        ini_set('memory_limit', '4G');
        $discoverHelpers = new Crawler_Company_MediaMarktAt_DiscoverHelpers();
        $articles = new Marktjagd_Collection_Api_Article();

        $campaigns = $discoverHelpers->getCampaignData();

        foreach ($campaigns as $campaign) {
            $campaignFiles = $discoverHelpers->downloadCampaignFiles($companyId, $campaign);

            $articlesData = $discoverHelpers->getArticlesData($campaign, $campaignFiles['productFeed'], $campaignFiles['articleListFile']);
            foreach ($articlesData as $articleData) {
                $article = $this->createArticle($articleData);
                if (preg_match('#x#', $campaign['needsDuplicate'])) {
                    $articleData['number'] .= '_dup';
                    $duplicateArticle = $this->createArticle($articleData);
                    $articles->addElement($duplicateArticle, false, 'complex', false);
                }
                $articles->addElement($article, false, 'complex', false);
            }
        }
        return $this->getResponse($articles, $companyId);
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();

        return $article->setArticleNumber($articleData['number'])
            ->setTitle($articleData['title'])
            ->setText($articleData['text'])
            ->setImage($articleData['image'])
            ->setPrice($articleData['price'])
            ->setUrl($articleData['url'])
            ->setEan($articleData['ean'])
            ->setManufacturer($articleData['manufacturer'])
            ->setSuggestedRetailPrice($articleData['suggested_retail_price'])
            ->setStart($articleData['start'])
            ->setVisibleStart($articleData['visible_start'])
            ->setEnd($articleData['end'])
            ->setAdditionalProperties($articleData['additional_properties']);
    }
}
