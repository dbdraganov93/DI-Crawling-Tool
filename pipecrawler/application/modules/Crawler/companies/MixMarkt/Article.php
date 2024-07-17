<?php
/**
 * Article Crawler für MixMarkt (ID: 28835)
 */

class Crawler_Company_MixMarkt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPage = new Marktjagd_Service_Input_Page();

        $cStores = $sApi->findStoresByCompany($companyId);

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($cStores->getElements() as $eStore) {
            $this->_logger->info($companyId . ': opening ' . $eStore->getWebsite());
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#Angebote\s*\((\d+)\)#';
            if (!preg_match($pattern, $page, $articleAmountMatch)) {
                $this->_logger->info($companyId . ': unable to get article amount.');
                continue;
            }

            for ($i = 1; $i <= ceil($articleAmountMatch[1] / 16); $i++) {
                $this->_logger->info($companyId . ': opening ' . $eStore->getWebsite() . 'page/' . $i . '/');
                $sPage->open($eStore->getWebsite() . 'page/' . $i);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#(<a[^>]*class="item[^>]*popup-link".+?)<\/a#';
                if (!preg_match_all($pattern, $page, $articleMatches)) {
                    $this->_logger->err($companyId . ': unable to get any articles for store no. ' . $eStore->getStoreNumber());
                    continue;
                }

                foreach ($articleMatches[1] as $singleArticle) {
                    $pattern = '#<a[^>]*href="([^"]+?)"[^>]*title="([^"]+?)"#';
                    if (!preg_match($pattern, $singleArticle, $imageTitleMatch)) {
                        $this->_logger->err($companyId . ': unable to get title or image for ' . $eStore->getWebsite());
                        continue;
                    }

                    $eArticle = new Marktjagd_Entity_Api_Article();

                    $pattern = '#<div[^>]*class="note"[^>]*>\s*(.+?)\s*<\/div#';
                    if (preg_match($pattern, $singleArticle, $textMatch)) {
                        $eArticle->setText($textMatch[1]);
                    }

                    $pattern = '#<div[^>]*class="price"[^>]*>\s*([^<]+?)\s*\€?\s*<#';
                    if (preg_match($pattern, $singleArticle, $priceMatch)) {
                        $eArticle->setPrice($priceMatch[1]);
                    }

                    $pattern = '#<div[^>]*class="price-old"[^>]*>\s*<span[^>]*>\s*([^<]+?)\s*\€?\s*<#';
                    if (preg_match($pattern, $singleArticle, $suggestedRetailPriceMatch)) {
                        $eArticle->setSuggestedRetailPrice($suggestedRetailPriceMatch[1]);
                    }

                    $pattern = '#<div[^>]*class="note\s*time"[^>]*>\s*vom\s*([^\s]+?)\s*bis\s*([^<]+?)\s*<#';
                    if (preg_match($pattern, $singleArticle, $validityMatch)) {
                        $eArticle->setStart($validityMatch[1])
                            ->setEnd($validityMatch[2])
                            ->setVisibleStart($eArticle->getStart());
                    }

                    $eArticle->setImage($imageTitleMatch[1])
                        ->setTitle($imageTitleMatch[2])
                        ->setStoreNumber($eStore->getStoreNumber());

                    $cArticles->addElement($eArticle, TRUE);
                }
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }
}