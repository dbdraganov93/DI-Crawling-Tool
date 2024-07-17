<?php
/**
 * Article Crawler für Peek & Cloppenburg (ID: 28923)
 */

class Crawler_Company_PeekCloppenburg_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.peek-und-cloppenburg.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $trackingParam = '?cid=de.ext.prospekt.offerista.netzwerk.' . ((int)date('W') + 2957) . '.do.hm';

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>\s*Werbung#';
        if (!preg_match($pattern, $page, $adUrlMatch)) {
            throw new Exception($companyId . ': unable to get advertising url.');
        }

        $this->_logger->info($companyId . ': opening ' . $adUrlMatch[1]);
        $sPage->open($adUrlMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#Werbung\s*(\d{2}\.\d{2}\.\d{0,2})\s*-\s*(\d{2}\.\d{2}\.\d{2})\s*<#';
        if (!preg_match($pattern, $page, $validityMatch)) {
            throw new Exception($companyId . ': unable to get article validity.');
        }

        $pattern = '#<select[^>]*is="pagination"[^>]*>(.+?)<\/select>#';
        if (!preg_match($pattern, $page, $paginationListMatch)) {
            throw new Exception($companyId . ': unable to get pagination list.');
        }

        $pattern = '#<option[^>]*value="([^"]+?)"#';
        if (!preg_match_all($pattern, $paginationListMatch[1], $pageMatches)) {
            throw new Exception($companyId . ': unable to get any pages from list.');
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($pageMatches[1] as $singlePage) {
            $this->_logger->info($companyId . ': opening ' . $singlePage);
            $sPage->open($singlePage);
            $jArticleInfos = $sPage->getPage()->getResponseAsJson();

            foreach ($jArticleInfos->data->products as $singleJArticle) {
                $pattern = '#\/p\/([^\/]+)\/#';
                if (!preg_match($pattern, $singleJArticle->url, $articleNumberMatch)) {
                    $this->_logger->err($companyId . ': unable to get article number from url ' . $singleJArticle->url);
                    continue;
                }
                $eArticle = new Marktjagd_Entity_Api_Article();

                $eArticle->setTitle($singleJArticle->brand . ' ' . $singleJArticle->name)
                    ->setArticleNumber($articleNumberMatch[1])
                    ->setImage($singleJArticle->imageSrc)
                    ->setPrice(preg_replace('#\s*\€#', '', $singleJArticle->price->base))
                    ->setUrl($singleJArticle->url . $trackingParam)
                    ->setStart(preg_replace(['#(\d{2})$#', '#\.$#'], ['20$1', '.' . date('Y')], $validityMatch[1]))
                    ->setEnd(preg_replace(['#(\d{2})$#', '#\.$#'], ['20$1', '.' . date('Y')], $validityMatch[2]));

                if (count($singleJArticle->sizes)) {
                    $eArticle->setSize(implode(', ', $singleJArticle->sizes));
                }

                $cArticles->addElement($eArticle);
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }
}