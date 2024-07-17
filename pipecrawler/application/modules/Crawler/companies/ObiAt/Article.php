<?php
/**
 * Article Crawler für OBI AT (ID: 73321)
 */

class Crawler_Company_ObiAt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.obi.at/';
        $searchUrl = $baseUrl . 'campaign/asdp_unsere_bestseller';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<ul[^>]*class="[^"]*products[^>]*>(.+?)<\/ul>#';
        if (!preg_match($pattern, $page, $articleListMatch)) {
            throw new Exception($companyId . ': unable to get article list.');
        }

        $pattern = '#<li[^>]*>(.+?)<\/li>#';
        if (!preg_match_all($pattern, $articleListMatch[1], $articleMatches)) {
            throw new Exception($companyId . ': unable to get any articles from list.');
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($articleMatches[1] as $singleArticle) {
            $pattern = '#<img[^>]*title="([^"]+?)"#';
            if (!preg_match($pattern, $singleArticle, $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get article title.');
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $pattern = '#<span[^>]*class="price-new"[^>]*data-csscontent="\€?\s*([^"]+?)"#';
            if (preg_match($pattern, $singleArticle, $priceMatch)) {
                $eArticle->setPrice($priceMatch[1]);
            }

            $pattern = '#<span[^>]*class="price-old"[^>]*>\s*<del[^>]*>\s*<span[^>]*data-csscontent="[^\€"]*\€?\s*([^"]+?)"#';
            if (preg_match($pattern, $singleArticle, $suggestedRetailPriceMatch)) {
                $eArticle->setSuggestedRetailPrice($suggestedRetailPriceMatch[1]);
            }

            $pattern = '#^<a[^>]*href="\/([^"]+?)"#';
            if (preg_match($pattern, $singleArticle, $urlMatch)) {
                $eArticle->setUrl($baseUrl . $urlMatch[1]);
            }

            $eArticle->setTitle($titleMatch[1]);

            $artUrl = $eArticle->getUrl();
            if (strlen($artUrl)) {
                $eArticle->setArticleNumber(basename($artUrl));

                $sPage->open($artUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#data-bigpic="([^"]+?)"#';
                if (preg_match($pattern, $page, $imageMatch)) {
                    $eArticle->setImage('https:' . $imageMatch[1]);
                }

                $pattern = '#"description":"([^"]+?)"#';
                if (preg_match($pattern, $page, $textMatch)) {
                    $eArticle->setText($textMatch[1]);
                }
            }

            $eArticle->setStart(date('d.m.Y', strtotime('monday this week')))
                ->setEnd(date('d.m.Y', strtotime('sunday this week')));

            $cArticles->addElement($eArticle, true, 'complex', false);
        }

        return $this->getResponse($cArticles);
    }
}