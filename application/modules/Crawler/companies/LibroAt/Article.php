<?php

/**
 * Article Crawler for Libro AT (ID: 73271)
 */

class Crawler_Company_LibroAt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.libro.at/';
        $aSearchUrls = [
            $baseUrl . 'schule.html',
            $baseUrl . 'buro.html',
            $baseUrl . 'buch.html',
//            $baseUrl . 'musik.html',
            $baseUrl . 'toys.html',
            $baseUrl . 'film.html',
            $baseUrl . 'games.html',
            $baseUrl . 'technik.html',
            $baseUrl . 'trends.html'
        ];
        $sPage = new Marktjagd_Service_Input_Page();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aSearchUrls as $searchUrl) {
            $this->_logger->info($companyId . ': opening ' . $searchUrl);
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="slider-container"[^>]*>(.+?)decorateGeneric#';
            if (!preg_match($pattern, $page, $articeListMatch)) {
                throw new Exception($companyId . ': unable to get article list ' . $searchUrl);
            }

            $pattern = '#<li[^>]*class="item"[^>]*>(.+?)<span[^>]*class="tax-details"[^>]*>#';
            if (!preg_match_all($pattern, $articeListMatch[1], $articleMatches)) {
                throw new Exception($companyId . ': unable to get any articles from list ' . $searchUrl);
            }

            foreach ($articleMatches[1] as $singleArticle) {
                $pattern = '#<a[^>]*href="([^"]+?)"[^>]*title="([^"]+?)"#';
                if (!preg_match($pattern, $singleArticle, $urlTitleMatch)) {
                    $this->_logger->err($companyId . ': unable to get article url or title: ' . $singleArticle);
                    continue;
                }

                $eArticle = new Marktjagd_Entity_Api_Article();

                $eArticle->setUrl($urlTitleMatch[1])
                    ->setTitle($urlTitleMatch[2]);

                if (strlen($eArticle->getUrl())) {
                    $sPage->open($eArticle->getUrl());
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#<span[^>]*id="product-price[^>]*>\s*([^<]+?)\s*\€?\s*<#';
                    if (preg_match($pattern, $page, $priceMatch)) {
                        $eArticle->setPrice($priceMatch[1]);
                    }

                    $pattern = '#<span[^>]*id="old-price[^>]*>\s*([^<]+?)\s*\€?\s*<#';
                    if (preg_match($pattern, $page, $suggestedRetailPriceMatch)) {
                        $eArticle->setSuggestedRetailPrice($suggestedRetailPriceMatch[1]);
                    }

                    $pattern = '#<th[^>]*>\s*(Marke|Artikelnummer|EAN\s*Code)<\/th>\s*<td[^>]*>\s*([^<]+?)\s*<#';
                    if (!preg_match_all($pattern, $page, $infoMatches)) {
                        $aInfos = array_combine($infoMatches[1], $infoMatches[2]);
                    }

                    $pattern = '#<div[^>]*class="highslide-gallery"[^>]*>\s*<div[^>]*class="prolabel-wrapper"[^>]*>\s*<a[^>]*href="([^"]+?)"#';
                    if (preg_match($pattern, $page, $imageMatch)) {
                        $eArticle->setImage($imageMatch[1]);
                    }

                    $eArticle->setManufacturer($aInfos['Marke'])
                        ->setArticleNumber($aInfos['Artikelnummer'])
                        ->setEan($aInfos['EAN Code']);

                }

                $cArticles->addElement($eArticle);
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }
}