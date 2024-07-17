<?php

/**
 * Artikel Crawler für Baby One AT (ID: 73170)
 */

class Crawler_Company_BabyOneAt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.babyone.at/';
        $searchUrl = $baseUrl . 'jubidu-de-at';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#>\s*(\d{2}\.\d{2}\.?)(\d{4})?\s*bis\s*(\d{2}\.\d{2}\.\d{4})\s*<#';
        if (!preg_match($pattern, $page, $validityMatch)) {
            throw new Exception($companyId . ': unable to get article validity.');
        }

        if (!preg_match('#\.$#', $validityMatch[1])) {
            $validityMatch[1] .= '.';
        }

        if (!strlen($validityMatch[2])) {
            $validityMatch[2] = date('Y');
        }

        $pattern = '#<div[^>]*class="product-name"[^>]*>\s*<a[^>]*class="name-link"[^>]*href="\/([^"]+?)"#';
        if (!preg_match_all($pattern, $page, $articleUrlMatches)) {
            throw new Exception($companyId . ': unable to get any article urls.');
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($articleUrlMatches[1] as $singleArticleUrl) {
            $articleDetailUrl = $baseUrl . $singleArticleUrl;

            $sPage->open($articleDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<meta[^>]*property="og:title"[^>]*content="([^"]+?)"#';
            if (!preg_match($pattern, $page, $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get article title: ' . $articleDetailUrl);
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $pattern = '#<div[^>]*class="product-brand"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $manufacturerMatch)) {
                $eArticle->setManufacturer($manufacturerMatch[1]);
            }

            $pattern = '#<meta[^>]*name="description"[^>]*content="([^"]+?)"#';
            if (preg_match($pattern, $page, $textMatch)) {
                $eArticle->setText($textMatch[1]);
            }

            $pattern = '#<span[^>]*class="price-sales"[^>]*>\s*(<span[^>]*addendum[^>]*>\s*[^<]+?<\/span>\s*)?\€?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $priceMatch)) {
                $eArticle->setPrice($priceMatch[2]);
            }

            $pattern = '#<span[^>]*class="price-standard"[^>]*>\s*\€?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $suggestedRetailPriceMatch)) {
                $eArticle->setSuggestedRetailPrice($suggestedRetailPriceMatch[1]);
            }

            $pattern = '#<meta[^>]*property="og:image"[^>]*content="([^"]+?)"#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eArticle->setImage($imageMatch[1]);
            }

            $eArticle->setTitle($titleMatch[1])
                ->setUrl($baseUrl . $singleArticleUrl)
                ->setStart($validityMatch[1] . $validityMatch[2])
                ->setEnd($validityMatch[3])
                ->setVisibleStart($eArticle->getStart());

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}