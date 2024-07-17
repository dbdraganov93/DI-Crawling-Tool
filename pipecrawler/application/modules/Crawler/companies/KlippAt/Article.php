<?php
/**
 * Brochure Crawler für Klipp WGW (ID: 73269)
 */

class Crawler_Company_KlippAt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sPage->open('https://friseur-produkte.at/feeds/wogibtswas.xml');
        $tracking = '?utm_source=wogibtswas&utm_campaign=maerz&utm_medium=cpv?etcc_cmp=maerz&etcc_med=cpv&etcc_grp=&etcc_par=wogibtswas&etcc_ctv=&etcc_plc=zB';

        $items = simplexml_load_string($sPage->getPage()->getResponseBody());
        $cArticles = new Marktjagd_Collection_Api_Article();

        $namespaces = $items->channel->getNameSpaces(true);
        $nse = $items->channel->children($namespaces['g']);

        $aArticles = [];
        foreach ($nse as $item) {
            $n = $item->getNameSpaces(true);
            $gNameSpaceElements = $item->children($n['g']);
            $eArticle = new Marktjagd_Entity_Api_Article();

            try {
                $eArticle->setArticleNumber((string)$gNameSpaceElements->id)
                    ->setTitle((string)$gNameSpaceElements->title)
                    ->setText((string)$gNameSpaceElements->description)
                    ->setUrl((string)$gNameSpaceElements->link . $tracking)
                    ->setManufacturer((string)$gNameSpaceElements->brand)
                    ->setEan((string)$gNameSpaceElements->ean)
                    ->setPrice((string)$gNameSpaceElements->price)
                    ->setImage((string)$gNameSpaceElements->image_link);

                if (!empty((string)$gNameSpaceElements->original_price) && floatval((string)$gNameSpaceElements->price) < floatval((string)$gNameSpaceElements->original_price)) {
                    $eArticle->setSuggestedRetailPrice((string)$gNameSpaceElements->original_price);
                }

                $cArticles->addElement($eArticle);

            } catch (Exception $e) {
                $this->_logger->warn('Exception: ' . $e->getMessage());
                var_dump($gNameSpaceElements);
                continue;
            }
            if (!array_key_exists($eArticle->getEan(), $aArticles)) {
                $aArticles[$eArticle->getEan()] = $eArticle;
            } elseif ($eArticle->getArticleNumber() > $aArticles[$eArticle->getEan()]->getArticleNumber()) {
                $aArticles[$eArticle->getEan()] = $eArticle;
            }
        }

        foreach ($aArticles as $singleArticle) {
            $cArticles->addElement($singleArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}
