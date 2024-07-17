<?php
/**
 * Discover Crawler for Office1BG (ID: 80516)
 */

class Crawler_Company_Office1BG_DiscoverArticle extends Crawler_Generic_Company
{
    private const DATE_FORMAT = 'd.m.Y';
    public const DISCOVER_ARTICLES = '19MNBz_hmCQfJLQdvT-zRYH1oh8wxJdRtgaBtDOFnmuE';
    public const DISCOVER_ARTICLE_PREFIX = 'DISCOVER_';
    protected string $startDate;
    protected string $endDate;

    public function crawl($companyId)
    {
        $this->crateValidityDate();
        $discoverArticles = $this->getDiscoverData();
        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($discoverArticles as $article) {
            $eArticle = $this->createArticle($article);
            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

    protected function getDiscoverData(): array
    {
        $googleReader = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        return $googleReader->getFormattedInfos(self::DISCOVER_ARTICLES, 'A1', 'W');
    }

    private function crateValidityDate(): void
    {
        // articles can be updated until Friday for current week
        $week = date('N') <= 5 ? 'this' : 'next';
        $this->startDate = date(self::DATE_FORMAT, strtotime('monday ' . $week . ' week')) . ' 00:00:00' ;
        // need to start from sunday but BT import date and chang it to monday, because of that we changed to saturday
        //$this->endDate = date(self::DATE_FORMAT, strtotime('saturday ' . $week . ' week')) . ' 23:59:59';
        $this->endDate = date(self::DATE_FORMAT,strtotime('+1 week',strtotime('saturday ' . $week . ' week'))) . ' 23:59:59';
    }

    private function createArticle(array $article): Marktjagd_Entity_Api_Article
    {
        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setArticleNumber(self::DISCOVER_ARTICLE_PREFIX . $article['articleNumber'])
            ->setTitle($article['title'])
            ->setText($article['text'])
            ->setSuggestedRetailPrice($article['suggestedRetailPrice'])
            ->setPrice($article['price'])
            ->setSize($article['unit'])
            ->setUrl($article['url'])
            ->setImage($article['image1'])
            ->setStart($this->startDate)
            ->setEnd($this->endDate)
            ->setVisibleStart($this->startDate);

        return $eArticle;
    }
}
