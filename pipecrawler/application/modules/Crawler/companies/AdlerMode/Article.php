<?php

/*
 * Artikel Crawler für Adler Mode (ID: 28950)
 */

class Crawler_Company_AdlerMode_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.adlermode.com/';
        $searchUrl = $baseUrl . 'neuheiten';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/([^"]+?neu-im-shop)"#';
        if (!preg_match_all($pattern, $page, $urlMatches)) {
            throw new Exception($companyId . ': unable to get any urls for sale.');
        }

        $urlsToCheck = array_unique($urlMatches[1]);

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($urlsToCheck as $singleUrlToCheck) {
            $articleInfoUrl = $baseUrl . $singleUrlToCheck;

            $sPage->open($articleInfoUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<span[^>]*class="pagingInfo"[^>]*>Seite\s*\d\s*von\s*(\d+)\s*<#';
            if (!preg_match($pattern, $page, $pagingMatch)) {
                $this->_logger->info($companyId . ': unable to get page max number.');
                $maxPage = 1;
            } else {
                $maxPage = $pagingMatch[1];
            }


            for ($i = 0; $i < $maxPage; $i++) {
                $sPage->open($articleInfoUrl . '?page=' . $i);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<div[^>]*class="product-listing[^>]*>\s*<a[^>]*href="\/([^\?]+?)\?.+?<img[^>]*src="[^"]+?(YzZA|ZjZQ|Y3Nzc)"[^>]*class="badge-image"[^>]*>#s';
                if (!preg_match_all($pattern, $page, $articleUrlMatches)) {
                    $this->_logger->info($companyId . ': unable to get articles from page ' . $articleInfoUrl . '?page=' . $i);
                    continue;
                }
                
                foreach ($articleUrlMatches[1] as $singleArticleUrl) {
                    $articleDetailUrl = $baseUrl . $singleArticleUrl;

                    $sPage->open($articleDetailUrl);
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#class="product-name"[^>]*itemprop="name"[^>]*>\s*([^<]+?)\s*<#';
                    if (!preg_match($pattern, $page, $nameMatch)) {
                        $this->_logger->err($companyId . ': unable to get article title: ' . $articleDetailUrl);
                        continue;
                    }

                    $eArticle = new Marktjagd_Entity_Api_Article();

                    $pattern = '#itemprop="price"[^>]*content="([^"]+?)"#';
                    if (preg_match($pattern, $page, $priceMatch)) {
                        $eArticle->setPrice($priceMatch[1]);
                    }

                    $pattern = '#itemprop="productID"[^>]*>\s*(\d+?)\s*<#';
                    if (preg_match($pattern, $page, $articleNumberMatch)) {
                        $eArticle->setArticleNumber($articleNumberMatch[1]);
                    }

                    $pattern = '#itemprop="color"[^>]*>\s*([^<]+?)\s*<#';
                    if (preg_match($pattern, $page, $colorMatch)) {
                        $eArticle->setColor($colorMatch[1]);
                    }

                    $pattern = '#<\/span>\s*<\/span>\s*<ul[^>]*>\s*(.+?)\s*<\/ul#';
                    if (preg_match($pattern, $page, $textMatch)) {
                        $pattern = '#<li[^>]*>\s*([^<]+?)\s*</li#';
                        if (preg_match_all($pattern, $textMatch[1], $textMatches)) {
                            $eArticle->setText(implode('<br/>', $textMatches[1]));
                        }
                    }

                    $pattern = '#<img[^>]*class="product-thumbnail-image"[^>]*src="([^"]+?)"#';
                    if (preg_match($pattern, $page, $imageMatch)) {
                        $eArticle->setImage('https:' . $imageMatch[1]);
                    }

                    $pattern = '#<span[^>]*class="price-euro[^>]*price-strike[^>]*price-large"[^>]*>\s*(.+?)\s*<\/sup>#';
                    if (preg_match($pattern, $page, $suggestedRetailPriceMatch)) {
                        $eArticle->setSuggestedRetailPrice(preg_replace('#\s*<[^>]*>\s*#', '.', $suggestedRetailPriceMatch[1]));
                    }

                    $eArticle->setTitle($nameMatch[1])
                            ->setUrl($articleDetailUrl);

                    $cArticles->addElement($eArticle);
                }
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }

}
