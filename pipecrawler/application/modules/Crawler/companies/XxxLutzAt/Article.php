<?php

/**
 * Artikelcrawler für XXXLutz AT (ID: 72280)
 */
class Crawler_Company_XxxLutzAt_Article extends Crawler_Generic_Company
{
    private const PRODUCTS_FEED_URL = 'https://transport.productsup.io/8ef6db46576c65d82149/channel/540807/xxxlutz_at_oth_wogibtswas.csv';

    public function crawl($companyId)
    {
        ini_set('memory_limit', '2G');
        $httpTransfer = new Marktjagd_Service_Transfer_Http();
        $spreadsheetReader = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $httpTransfer->generateLocalDownloadFolder($companyId);
        $localArticleFile = $httpTransfer->getRemoteFile(self::PRODUCTS_FEED_URL, $localPath);

        $articlesData = $spreadsheetReader->readFile($localArticleFile, TRUE, ';')->getElement(0)->getData();

        $articles = new Marktjagd_Collection_Api_Article();
        foreach ($articlesData as $articleData) {
            if (!preg_match('#in\s*stock#i', $articleData['verfuegbarkeit'])) {
                continue;
            }

            $article = $this->createArticle($articleData);
            $articles->addElement($article);
        }

        return $this->getResponse($articles, $companyId);
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        $article->setArticleNumber($articleData['ArtikelID'])
            ->setTitle($articleData['Artikelbezeichnung'])
            ->setText(preg_replace('#[^\w\d\s\.,\!\?:äöüß]#i', '', $articleData['Beschreibung']) . '<br/>' . $articleData['Kategorie'])
            ->setPrice($articleData['Preis'])
            ->setManufacturer($articleData['Hersteller'])
            ->setUrl($articleData['Deeplink'])
            ->setEan($articleData['EAN_Code'])
            ->setImage($articleData['bild_gross'])
            ->setSuggestedRetailPrice($articleData['Stattpreis']);

        return $article;
    }
}
