<?php
/**
 * Article Crawler für TEDi AT (ID: )
 */

class Crawler_Company_TediAt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.tedi.com/';
        $searchUrl = $baseUrl . 'at/angebote-aktionen/angebot-des-monats/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/([^"]+?)"[^>]*>\s*Mehr\s*anzeigen\s*<\/a#';
        if (!preg_match_all($pattern, $page, $articlePageUrlMatches)) {
            throw new Exception($companyId . ': unable to get any article pages.');
        }

        $aUrls = [];
        foreach ($articlePageUrlMatches[1] as $singleArticlePageUrl) {
            $articlesOverviewPageUrl = $baseUrl . $singleArticlePageUrl;

            $sPage->open($articlesOverviewPageUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="product-list"[^>]*>(.+?)<button#';
            if (!preg_match($pattern, $page, $articleListMatch)) {
                $this->_logger->err($companyId . ': unable to get article list: ' . $articlesOverviewPageUrl);
                continue;
            }

            $pattern = '#<a[^>]*class="product-list__item"[^>]*href="\/([^"]+?)"#';
            if (!preg_match_all($pattern, $articleListMatch[1], $articleUrlMatches)) {
                $this->_logger->err($companyId . ': unable to get any articles from list: ' . $articlesOverviewPageUrl);
                continue;
            }

            foreach ($articleUrlMatches[1] as $singleArticleUrl) {
                $aUrls[] = $baseUrl . $singleArticleUrl;
            }
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="section\s*section--fullwidth"[^>]*>(.+?)<\/span>\s*<\/div>#';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get info list: ' . $singleUrl);
                continue;
            }

            $pattern = '#<img[^>]*src="\/([^"]+?)"#';
            if (!preg_match($pattern, $infoListMatch[1], $imageMatch)) {
                $this->_logger->err($companyId . ': unable to get article image: ' . $singleUrl);
                continue;
            }

            $pattern = '#<h1[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $infoListMatch[1], $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get article title: ' . $singleUrl);
                continue;
            }

            $pattern = '#<strong[^>]*>\s*(je)?\s*([^\€]+?)\s*\€\s*<#';
            if (!preg_match($pattern, $infoListMatch[1], $priceMatch)) {
                $this->_logger->err($companyId . ': unable to get article price: ' . $singleUrl);
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $pattern = '#<p[^>]*class="product__description"[^>]*>\s*(.+?)\s*<\/p#';
            if (preg_match($pattern, $infoListMatch[1], $textMatch)) {
                $eArticle->setText($textMatch[1]);
            }

            $eArticle->setTitle($titleMatch[1])
                ->setImage($baseUrl . $imageMatch[1])
                ->setPrice($priceMatch[2])
                ->setUrl($singleUrl);

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}