<?php

/**
 * Article Crawler für denn's Biomarkt AT (ID: 72801)
 */
class Crawler_Company_DennsBiomarktAt_Article extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $numberOfItems  = 100;
        $sPage          = new Marktjagd_Service_Input_Page();
        $cArticles      = new Marktjagd_Collection_Api_Article();
        $baseUrl        = 'https://www.denns-biomarkt.at/';
        $thisYear       = date("Y");
        $nextYear = (date( "Y", strtotime('+1 year')));
        $jsonResponse   = $this->createRequest($numberOfItems, $sPage, $baseUrl);

        // check total number of products to check if another request needed
        if($jsonResponse->results->numberOfResults > $numberOfItems) {
            $jsonResponse = $this->createRequest($jsonResponse->results->numberOfResults, $sPage, $baseUrl);
        }

        if(empty($jsonResponse)) {
            throw new Exception('Json response is empty');
        }

        foreach ($jsonResponse->results->resultDocuments as $article) {
            if ($article->pricePrefix == '' || $article->pricePrefix == 0) {
                $this->_logger->alert('The product ' . $article->title . ' does not have price and was skipped');
                continue;
            }
            if($article->title == '') {
                $this->_logger->alert('The product does not have title and was skipped');
                continue;
            }

            $validityPattern = '#Gültig\s*vom\s*(?<fromDate>\d{2}.\d{2}.)\s*-?\s*(?<toDate>\d{2}.\d{2}.)#';
            if(!preg_match($validityPattern, $article->eyecatcher, $dateMatch)) {
                $dateMatch['fromDate'] = (new DateTime('now'))->format('d.m') . '.';
                $dateMatch['toDate'] = (new DateTime('+5 day'))->format('d.m') . '.';
                $this->_logger->info(
                    'The product ' . $article->title .
                    ' does not have valid until date and the default was used (from today until ' .
                    $dateMatch['toDate'] . ')'
                );
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($article->uid)
                ->setTitle($article->title)
                ->setText($article->shortDescription . ' ' .$article->subtitle)
                ->setPrice($article->pricePrefix . ',' . $article->priceSuffix)
                ->setUrl(urldecode($article->sharingLink))
                ->setImage($baseUrl . $article->img->srcSet[3]->src)
                ->setStart($dateMatch['fromDate'].$thisYear)
                ->setEnd($dateMatch['toDate'].$nextYear)
            ;

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

    private function createRequest(int $numberOfItems, Marktjagd_Service_Input_Page $sPage, $baseUrl)
    {
        $searchUrl      = $baseUrl . 'angebote/?eID=apertoSearchResults&type=offer&pageId=1321&limit=' . $numberOfItems
            . '&total=0&offset=0&usermarket=0&offerperiodstate=current&q=';

        $sPage->open($searchUrl);

        return $sPage->getPage()->getResponseAsJson();
    }
}
