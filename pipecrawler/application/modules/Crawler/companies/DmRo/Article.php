<?php
/**
 * Article Crawler für DM AT (ID: 80418)
 */

class Crawler_Company_DmRo_Article extends Crawler_Generic_Company
{
    private const ARTICLE_FEED = 'https://www.semtrack.de/e?i=1b77bf2b10cae5d884274ec65a677969f668f3f5';
    private const ARTICLE_LIMIT = 200;

    public function crawl($companyId)
    {
        $articles = new Marktjagd_Collection_Api_Article();
        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();

        $productFeedFile = $this->downloadArticleFeed($companyId);
        $articlesData = $sPhpSpreadsheet->readFile($productFeedFile, TRUE, "\t")->getElement(0)->getData();

        $articleCount = 0;
        foreach ($articlesData as $articleData) {
            if ($articleCount++ >= self::ARTICLE_LIMIT) {
                break;
            }
            $article = $this->createArticle($articleData);
            $articles->addElement($article, TRUE, 'simple', FALSE);
        }

        return $this->getResponse($articles, $companyId);
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        $article->setArticleNumber($articleData['id'])
            ->setTitle($articleData['title'])
            ->setText($articleData['description'])
            ->setPrice($articleData['price'])
            ->setManufacturer($articleData['brand'])
            ->setUrl($articleData['link'])
            ->setImage($articleData['image_link']);

        return $article;
    }

    private function downloadArticleFeed(int $companyId): string
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        return $sHttp->getRemoteFile(self::ARTICLE_FEED, $localPath);
    }
}
