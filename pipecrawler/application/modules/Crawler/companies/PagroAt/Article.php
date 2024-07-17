<?php
/**
 * Article Crawler für Pagro AT (ID: 72441)
 */

class Crawler_Company_PagroAt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.pagro.at/';
        $aSearchUrls = [
            $baseUrl . 'buro-1/',
            $baseUrl . 'schule-1/',
            $baseUrl . 'schenken-kreativ/',
            $baseUrl . 'haushalt-wohnen/',
            $baseUrl . 'angebote/'
        ];
        $sPage = new Marktjagd_Service_Input_Page();


        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aSearchUrls as $searchUrl) {
            $pageCount = $this->getPageNumber($searchUrl, $sPage, $companyId);


            for ($i = 1; $i <= $pageCount; $i++) {
                $json = $this->getPagesJson($searchUrl, $i, $sPage);
                if (is_null($json)
                    || !property_exists($json, 'product')
                    || !property_exists($json->product, 'list')
                    || !property_exists($json->product->list, 'items')) {
                    continue;
                }

                foreach ($json->product->list->items as $product) {
                    $eArticle = new Marktjagd_Entity_Api_Article();

                    $eArticle->setArticleNumber($product->id)
                        ->setUrl('https://pagro.at/' . $product->url_path)
                        ->setPrice($product->regular_price)
                        ->setTitle($product->name);

                    if (property_exists($product, 'short_description')) {
                        $eArticle->setText($product->short_description);
                    }
                    if (property_exists($product, 'image')) {
                        $eArticle->setImage('https://pagro.at/img/600/744/resize' . $product->image);
                    }

                    $cArticles->addElement($eArticle, true, 'complex', false);
                }
            }
        }


        return $this->getResponse($cArticles, $companyId);
    }

    private function getPageNumber(string $url, Marktjagd_Service_Input_Page $sPage, int $companyId): int
    {
        sleep(1);
        $page = $sPage->getDomElsFromUrlByClass($url, 'toolbar-pagination__inner');
        $pageCount = '';
        $tmpPageCount = $page[0]->nodeValue;
        if (!preg_match('#\.(\d+)#', $tmpPageCount)) {
            $pageCount = substr(trim($tmpPageCount), -1);
        } else {
            preg_match('#\.(\d+)#', $tmpPageCount, $pageCount);
            $pageCount = $pageCount[1];
        }
        if (empty($pageCount)) {
            throw new Exception($companyId . ': unable to get the total amount of articles.');
        }

        return $pageCount;
    }

    private function getPagesJson(string $url, int $pageNumber, Marktjagd_Service_Input_Page $sPage): ?object
    {
//        var_dump($url . '?p=' . $pageNumber);

        $oPage = $sPage->getPage();
        $oPage->setTimeout(120);
        $oPage->setLoadTries(3);
        $sPage->setPage($oPage);
//        $start = microtime(true);
        try {
            $sPage->open($url . '?p=' . $pageNumber);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
//        $end = microtime(true);
//        $time = $end - $start;
//        echo "load Time: {$time} \n";

        $rawPage = $sPage->getPage()->getResponseBody();


        $pattern = '#STATE__=(.*);\(fun#';
        if (!preg_match($pattern, $rawPage, $rawJson)) {
            $this->_logger->err('unable to get stores for page.');
            return null;
        }
        return json_decode($rawJson[1]);
    }

}


