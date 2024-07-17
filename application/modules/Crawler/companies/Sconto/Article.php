<?php

/**
 * Artikel Crawler für Sconto Möbel-Sofort (ID: 156)
 */
class Crawler_Company_Sconto_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.sconto.de/';
        $priceUrl = $baseUrl . '/catalog/product/price-container/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#kategorien.+?<ul[^>]*>(.+?)</ul#is';
        if (!preg_match($pattern, $page, $categoryListMatch))
        {
            throw new Exception($companyId . ': unable to get article category list.');
        }

        $pattern = '#<li[^>]*>\s*<a[^>]*href="\/([^"]+?)"[^>]*>\s*<span>[^<]+?</span>\s*</a>\s*</li>\s*<hr>#';
        if (!preg_match_all($pattern, $categoryListMatch[1], $categoryMatches))
        {
            throw new Exception($companyId . ': unable to get any categories from article category list.');
        }

        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($categoryMatches[1] as $singleCategory)
        {
            $site = 1;
            while (true)
            {
                Zend_Debug::dump($baseUrl . $singleCategory . '?limit=0&page=' . $site);
                $sPage->open($baseUrl . $singleCategory . '?limit=0&page=' . $site++);
                $page = $sPage->getPage()->getResponseBody();

                if (!preg_match_all('#<div[^>]*class="item-photo"[^>]*>\s*<a[^>]*href="(/produkte/[^"]*)">#', $page, $linkMatches))
                {
                    $this->_logger->info($companyId . ': unable to get any article links');
                    break;
                }

                $linkMatches[1] = array_unique($linkMatches[1]);

                foreach ($linkMatches[1] as $link)
                {
                    $this->_logger->info('open ' . $link);
                    $sPage->open($baseUrl . $link);
                    $page = $sPage->getPage()->getResponseBody();

                    $eArticle = new Marktjagd_Entity_Api_Article();

                    $eArticle->setUrl($baseUrl . $link);

                    if (preg_match('#<p[^>]*class="article"[^>]*>([^<]+)</p>#', $page, $match))
                    {
                        $eArticle->setArticleNumber(trim($match[1]));
                    }

                    if (preg_match('#<h3[^>]*id="prd_name"[^>]*>([^<]+)</h3>#', $page, $match))
                    {
                        $eArticle->setTitle(trim($match[1]));
                    }

                    if (preg_match('#<strong[^>]*id="prd_name2"[^>]*>([^<]+)</strong>#', $page, $match))
                    {
                        $eArticle->setTitle($eArticle->getTitle() . ' (' . trim($match[1]) . ')');
                    }

                    if (preg_match('#<div[^>]*id="description"[^>]*>.+?<p[^>]*>(.+?)</p>#', $page, $match))
                    {
                        $eArticle->setText(trim($match[1]));
                    }

                    if (preg_match('#<ul[^>]*class="list-attr-short_description"[^>]*>(.+?)</ul>#', $page, $match))
                    {
                        $eArticle->setText($eArticle->getText() . '<br/><br/><ul>' . $match[1] . '</ul>');
                    }

                    if (preg_match('#<img[^>]*class="img-responsive gallery"[^>]*src="([^"]+)"#', $page, $match))
                    {
                        $eArticle->setImage($match[1]);
                    }

                    if (preg_match('#\'price\':\s*"([^"]+)"#', $page, $match))
                    {
                        $eArticle->setPrice(str_replace('.', '', $match[1]));
                    }

                    if (preg_match('#new\s+Product\(([0-9]+),#', $page, $match))
                    {
                        $sPage->open($priceUrl . $match[1]);
                        $pricePage = $sPage->getPage()->getResponseBody();

                        if (preg_match('#<div\s*class="[^"]*pdp-old-price[^"]*"[^>]*>([^<]+)<#', $pricePage, $match))
                        {
                            $oldPrice = str_replace('.', '', $match[1]);
                            $oldPrice = str_replace('-', '00', $oldPrice);
                            $eArticle->setSuggestedRetailPrice($oldPrice);
                        }
                    }

                    if (preg_match('#Farbe:\s*</div>\s*<div[^>]*>([^<]+)<#', $page, $match))
                    {
                        $eArticle->setColor(trim($match[1]));
                    }


                    if (preg_match('#Farbe:\s*</div>\s*<div[^>]*>([^<]+)<#', $page, $match))
                    {
                        $eArticle->setColor(trim($match[1]));
                    }

                    $sizeArray = array();

                    if (preg_match('#>\s*(L[^<]+nge):\s*</div>\s*<div[^>]*>([^<]+)<#', $page, $match))
                    {
                        $sizeArray[trim($match[1])] = trim($match[2]);
                    }

                    if (preg_match('#>\s*(Breite):\s*</div>\s*<div[^>]*>([^<]+)<#', $page, $match))
                    {
                        $sizeArray[trim($match[1])] = trim($match[2]);
                    }

                    if (preg_match('#>\s*(H[^<]+he):\s*</div>\s*<div[^>]*>([^<]+)<#', $page, $match))
                    {
                        $sizeArray[trim($match[1])] = trim($match[2]);
                    }

                    if (count($sizeArray))
                    {
                        $eArticle->setSize(implode(' x ', array_keys($sizeArray)) . ': ' . implode(' x ', $sizeArray));
                    }

                    $tags = array('Wohnung', 'Möbel', 'Einrichtung');
                    if (preg_match('#<div[^>]*id="navig-path"[^>]*>(.+?)</div>#', $page, $match))
                    {
                        $navWords = preg_split('#<[^>]+>#', preg_replace('#Startseite#i', '', $match[1]));

                        foreach ($navWords as $navWord)
                        {
                            if (strlen($navWord) > 3)
                            {
                                $tags[] = trim($navWord);
                            }
                        }
                    }

                    $eArticle->setTags(implode(',', array_unique($tags)));

                    $cArticles->addElement($eArticle);
                }

//                $pattern = '#limit=0&page=' . $site . '#';
//                
//                if (!preg_match($pattern, $page, $siteNumberMatch))
//                {
//                    break;
//                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
