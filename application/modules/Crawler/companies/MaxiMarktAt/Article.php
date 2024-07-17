<?php

/**
 * Article Crawler for Maxi Markt AT (ID: 72499)
 */
class Crawler_Company_MaxiMarktAt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.maximarkt.at/';
        $searchUrl = $baseUrl . 'schaufenster/?only_specials=1';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="product[^>]*product--primary[^>]*>(.+?)<\/small#';
        if (!preg_match_all($pattern, $page, $articleMatches)) {
            throw new Exception($companyId . ': unable to get any articles.');
        }

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $cArticlesApi = $sApi->getActiveArticleCollection($companyId);

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($cArticlesApi->getElements() as $eArticleApi) {
            if ($eArticleApi->getPrice() == 0) {
                $cArticles->addElement($eArticleApi);
            }
        }

        foreach ($articleMatches[1] as $singleArticle) {
            $pattern = '#<div[^>]*class="h4"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $singleArticle, $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get article title: ' . $singleArticle);
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $pattern = '#<a[^>]*href="([^"]+?)"#';
            if (preg_match($pattern, $singleArticle, $urlMatch)) {
                $eArticle->setUrl($urlMatch[1]);
            }

            $pattern = '#<img[^>]*src="\/([^"]+?)"#';
            if (preg_match($pattern, $singleArticle, $imageMatch)) {
                $eArticle->setImage($baseUrl . $imageMatch[1]);
            }

            $pattern = '#<p[^>]*(>.+?<)\/p#';
            if (preg_match($pattern, $singleArticle, $textListMatch)) {
                $pattern = '#>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $textListMatch[1], $textMatches)) {
                    $strText = '';
                    foreach ($textMatches[1] as $singleText) {
                        if (!preg_match('#^UVP#', $singleText)) {
                            if (strlen($strText)) {
                                $strText .= '<br/>';
                            }
                            $strText .= $singleText;
                        }
                    }
                }
                $eArticle->setText($strText);
            }

            $pattern = '#class="h5"[^>]*>\s*statt\s*(.+?)<\/sup#';
            if (preg_match($pattern, $singleArticle, $suggestedRetailPriceMatch)) {
                $eArticle->setSuggestedRetailPrice(preg_replace('#\s*<[^>]*>\s*#', ',', $suggestedRetailPriceMatch[1]));
            }

            $pattern = '#class="h1"[^>]*>\s*(.+?)\s*<\/sup#';
            if (preg_match($pattern, $singleArticle, $priceMatch)) {
                $eArticle->setPrice(preg_replace('#\s*<[^>]*>\s*#', ',', $priceMatch[1]));
            }

            $eArticle->setTitle($titleMatch[1]);

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}