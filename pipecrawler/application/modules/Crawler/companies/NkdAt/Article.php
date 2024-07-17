<?php

/**
 * Article Crawler for NKD AT (ID: 73284)
 */

class Crawler_Company_NkdAt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.nkd.com/';
        $searchUrl = $baseUrl . 'de_at/sale.html?___store=de_at&product_list_limit=80&p=1';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<p[^>]*class="toolbar-amount"[^>]*id="toolbar-amount">\s*(.+?<\/p>)#';
        if (!preg_match($pattern, $page, $totalAmountListMatch)) {
            throw new Exception($companyId . ': unable to get total amount list.');
        }

        $pattern = '#>\s*([^<]+?)\s*<\/span>\s*(Artikel)?\s*<\/p>$#';
        if (!preg_match($pattern, $totalAmountListMatch[1], $totalAmountMatch)) {
            throw new Exception($companyId . ': unable to get total amount from list.');
        }

        $pagesNeeded = ceil($totalAmountMatch[1] / 80);

        $cArticles = new Marktjagd_Collection_Api_Article();
        for ($i = 1; $i <= $pagesNeeded; $i++) {
            $searchUrl = $baseUrl . 'de_at/sale.html?___store=de_at&product_list_limit=80&p=' . $i;

            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="([^"]+?)"[^>]*class="cs-product-tile__name-link#';
            if (!preg_match_all($pattern, $page, $articleUrlMatches)) {
                $this->_logger->err($companyId . ': unable to get any article detail urls ' . $searchUrl);
                continue;
            }

            foreach ($articleUrlMatches[1] as $singleArticleUrl) {
                $sPage->open($singleArticleUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<picture[^>]*class="cs-product-gallery__placeholder-image[^"]*gallery-placeholder__image"[^>]*>\s*<source[^>]*srcset="([^"]+?)"[^>]*alt="([^"]+?)"#';
                if (!preg_match($pattern, $page, $imageTitleMatch)) {
                    $this->_logger->err($companyId . ': unable to get article image or title ' . $singleArticleUrl);
                }

                $eArticle = new Marktjagd_Entity_Api_Article();

                $pattern = '#<span[^>]*id="product-price[^>]*data-price-amount="([^"]+)"#';
                if (preg_match($pattern, $page, $priceMatch)) {
                    $eArticle->setPrice($priceMatch[1]);
                }

                $pattern = '#<span[^>]*id="old-price[^>]*data-price-amount="([^"]+)"#';
                if (preg_match($pattern, $page, $suggestedRetailPriceMatch)) {
                    $eArticle->setSuggestedRetailPrice($suggestedRetailPriceMatch[1]);
                }

                $pattern = '#<div[^>]*class="product attribute description"[^>]*>\s*<div[^>]*class="value"[^>]*>\s*(\s*<p[^>]*>.+<\/p>\s*)*\s*<ul[^>]*>(.+?)<\/ul#';
                if (preg_match($pattern, $page, $textListMatch)) {
                    $strText = '';
                    if (strlen($textListMatch[1])) {
                        $strText .= trim(strip_tags($textListMatch[1]));
                    }

                    $pattern = '#<li[^>]*>\s*(.+?)\s*<\/li>#';
                    if (preg_match_all($pattern, $textListMatch[2], $textMatches)) {
                        if (strlen($strText)) {
                            $strText .= '<br/><br/>';
                        }
                        $strText .= implode('<br/>', $textMatches[1]);
                    }

                    $eArticle->setText($strText);
                }

                $pattern = '#data-th="Art\.Nr\."[^>]*>\s*([^<]+?)\s*<\/td>#';
                if (preg_match($pattern, $page, $articleNumberMatch)) {
                    $eArticle->setArticleNumber($articleNumberMatch[1]);
                }

                $eArticle->setImage($imageTitleMatch[1])
                    ->setTitle($imageTitleMatch[2])
                    ->setUrl($singleArticleUrl)
                    ->setStart(date('d.m.Y', strtotime('monday this week')))
                    ->setEnd(date('d.m.Y', strtotime('saturday this week')));

                $cArticles->addElement($eArticle);
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }
}