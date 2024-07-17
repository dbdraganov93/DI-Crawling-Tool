<?php
/**
 * Article Crawler für DM AT (ID: 73424)
 */

class Crawler_Company_DmAt_Article extends Crawler_Generic_Company
{
    private const ARTICLE_FEED = 'https://www.semtrack.de/e?i=fbe0fb29ca85872378eb7b49d39421da637c656e';

    public function crawl($companyId)
    {
        $cArticles = new Marktjagd_Collection_Api_Article();
        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();

        $productFeedFile = $this->downloadArticleFeed($companyId);
        $articles = $sPhpSpreadsheet->readFile($productFeedFile, TRUE, "\t")->getElement(0)->getData();
        foreach ($articles as $article) {
            $eArticle = $this->createArticle($article);
            $cArticles->addElement($eArticle, TRUE, 'simple', FALSE);
        }
        return $this->getResponse($cArticles, $companyId);
    }

    private function createArticle(array $article): Marktjagd_Entity_Api_Article
    {
        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setArticleNumber($article['ArtikelID'])
            ->setTitle($article['Artikelbezeichnung'])
            ->setText($article['Beschreibung'] . '<br/><br/>Kategorie:<br/>' . $article['Kategorie'])
            ->setPrice(preg_replace(['#\s*EUR#', '#^\.#'], ['', '0.'], $article['Preis']))
            ->setManufacturer($article['Hersteller'])
            ->setUrl($article['Deeplink'])
            ->setEan($article['EAN_Code'])
            ->setImage($article['bild_gross']);

        return $eArticle;
    }

    private function downloadArticleFeed(int $companyId): string
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        return $sHttp->getRemoteFile(self::ARTICLE_FEED, $localPath);
    }
}