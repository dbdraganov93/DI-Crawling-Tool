<?php
/**
 * Products crawler for Lagerhaus AT (ID: 73029)
 */

class Crawler_Company_LagerhausAt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://lagerhaus.at/';
        $searchUrl = $baseUrl . 'bauen-garten/angebote';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/([^"]+?angebote[^"]+?)"#';
        if (!preg_match($pattern, $page, $articleMonthMatch)) {
            throw new Exception($companyId . ': unable to get url for monthly articles.');
        }

        $sPage->open($baseUrl . $articleMonthMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="(\?[^"]+?)"[^>]*class="sws-pagination__links__link"[^>]*>\s*\d+\s*<\/a#';
        if (!preg_match_all($pattern, $page, $paginationMatches)) {
            throw new Exception($companyId . ': unable to get pagination.');
        }

        $aUrls = [$baseUrl . $articleMonthMatch[1]];

        foreach ($paginationMatches[1] as $singlePagination) {
            $aUrls[] = $baseUrl . $articleMonthMatch[1] . $singlePagination;
        }

        $aArticleUrls = [];
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="\/([^"]+?)\s*"[^>]*data-controller="product-item"#';
            if (!preg_match_all($pattern, $page, $articleUrlMatches)) {
                $this->_logger->err($companyId . ': unable to get any article links.');
                continue;
            }

            $aArticleUrls = array_merge($aArticleUrls, $articleUrlMatches[1]);
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aArticleUrls as $singleArticleUrl) {
            $articleDetailUrl = $baseUrl . $singleArticleUrl;
            $sPage->open($articleDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="sws-content-wrapper"[^>]*>(.+?)<form#';
            if (!preg_match($pattern, $page, $articleInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get article info: ' . $articleDetailUrl);
                continue;
            }

            $pattern = '#producttitle"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $articleInfoMatch[1], $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get article title: ' . $articleDetailUrl);
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $pattern = '#<div[^>]*data-main-image[^>]*data-zoom-image-src="([^"]+?)"#';
            if (preg_match($pattern, $articleInfoMatch[1], $imageMatch)) {
                $eArticle->setImage($imageMatch[1]);
            }

            $pattern = '#specification"[^>]*>(.+?)<a#';
            if (preg_match($pattern, $articleInfoMatch[1], $textListMatch)) {
                $pattern = '#<ul[^>]*>(.+?)<\/ul#';
                if (preg_match_all($pattern, $textListMatch[1], $textMatches)) {
                    $eArticle->setText(implode('<br/>', $textMatches[1]));
                }
            }

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}