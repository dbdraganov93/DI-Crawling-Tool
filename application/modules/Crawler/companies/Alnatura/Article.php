<?php
/**
 * Artikel Crawler für Alnatura (ID: 22232)
 */

class Crawler_Company_Alnatura_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://angebote.alnatura.de/';
        $searchUrl = $baseUrl . 'de-de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTime = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*id="thisWeek"[^>]*>(.+?)<div[^>]*(id="nextWeek|id="thisMonth|class="m-flugblat-aktion-list-module__richtext-container")#s';
        if (!preg_match($pattern, $page, $articleListMatch)) {
            throw new Exception($companyId . ': unable to get article list.');
        }

        $pattern = '#<div[^>]*class="m-product-teaser"[^>]*>(.+?)<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>#s';
        if (!preg_match_all($pattern, $articleListMatch[1], $articleMatches)) {
            throw new Exception($companyId . ': unable to get any articles from list.');
        }

        $pattern = '#diese\s*Woche\s*von\s*([^\s]+?)\s*-\s*([^<]+?)\s*<#';
        if (!preg_match($pattern, $page, $validityMatch)) {
            throw new Exception($companyId . ': unable to get article validity.');
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($articleMatches[1] as $singleArticle) {
            $pattern = '#class="m-product-teaser[^"]*--(product-)?([^">]+?)(-blue)?"[^>]*>\s*([^<]{2,}?)\s*<#';
            if (!preg_match_all($pattern, $singleArticle, $articleInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get any article infos: ' . $singleArticle);
            }

            $aArticlesInfos = array_combine($articleInfoMatches[2], $articleInfoMatches[4]);

            $eArticle = new Marktjagd_Entity_Api_Article();

            $pattern = '#<img[^>]*data-srcset="\/([^"]+?)"#';
            if (preg_match($pattern, $singleArticle, $imageMatch)) {
                $eArticle->setImage($baseUrl . $imageMatch[1]);
            }

            $price = $aArticlesInfos['price-new'] ?: $aArticlesInfos['price-new-aubergine'];

            $eArticle->setManufacturer($aArticlesInfos['brand'])
                ->setTitle($aArticlesInfos['name'])
                ->setSuggestedRetailPrice(preg_replace('#\s*\€#', '', $aArticlesInfos['price-old']))
                ->setPrice(preg_replace('#\s*\€#', '', $price))
                ->setAmount($aArticlesInfos['amount'])
                ->setStart($validityMatch[1] . $sTime->getWeeksYear())
                ->setEnd($validityMatch[2]);

            $pattern = '#(Abtropfgewicht\s.+?)</div>#';
            if (preg_match($pattern, $singleArticle, $articleInfoMatches)) {
                $eArticle->setText($articleInfoMatches[1]);
            }

            if(!$cArticles->addElement($eArticle)) {
                Zend_Debug::dump($eArticle);
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }
}