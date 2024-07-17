<?php
/**
 * Article Crawler für Nah & Frisch AT (ID: 72708)
 */

class Crawler_Company_NahUndFrischAt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId): Crawler_Generic_Response
    {
        $baseUrl = 'https://www.nahundfrisch.at/';
        $searchUrl = $baseUrl . 'de/aktuelles/angebote-der-woche';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="block\s*offers\s*angebote-der-woche\s*current[^>]*>(.+?)<div[^>]*class="block\s*offers\s*angebote-der-woche\s*upcoming#';
        if (!preg_match($pattern, $page, $articleListMatch)) {
            throw new Exception($companyId . ': unable to get article list.');
        }

        $pattern = '#<i[^>]*calendar[^>]*>\s*<\/i>[^<]*von\s*(\d+[^\s]+)\s*bis\s*(\d[^\s]+)\s+#';
        if (!preg_match($pattern, $articleListMatch[1], $validityMatch)) {
            throw new Exception($companyId . ': unable to get article validity.');
        }

        $pattern = '#<div[^>]*class="block-item"[^>]*>(.+?"productname".+?<img[^>]*>)\s*<\/div#';
        if (!preg_match_all($pattern, $articleListMatch[1], $articleMatches)) {
            throw new Exception($companyId . ': unable to get any articles from list.');

        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($articleMatches[1] as $singleArticle) {
            $pattern = '#class="productname"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $singleArticle, $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get product title.');
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $pattern = '#class="content"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $singleArticle, $textMatch)) {
                $eArticle->setText($textMatch[1]);
            }

            $pattern = '#class="pricebase"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $singleArticle, $priceBaseMatch)) {
                if (strlen($eArticle->getText())) {
                    $eArticle->setText($eArticle->getText() . '<br/>');
                }
                $eArticle->setText($eArticle->getText() . $priceBaseMatch[1]);
            }

            $pattern = '#class="price\s*numb?eric"[^>]*>\s*(.+?)\s*<\/sup>\s*<\/div>#';
            if (preg_match($pattern, $singleArticle, $priceMatch)) {
                $eArticle->setPrice(preg_replace('#\s*<[^>]*>\s*#', '.', $priceMatch[1]));
            }

            $pattern = '#<img[^>]*src="([^"]+?)"#';
            if (preg_match($pattern, $singleArticle, $imageMatch)) {
                $eArticle->setImage($imageMatch[1]);
            }

            $eArticle->setTitle($titleMatch[1])
                ->setStart($validityMatch[1])
                ->setEnd($validityMatch[2])
                ->setVisibleStart($eArticle->getStart());

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}