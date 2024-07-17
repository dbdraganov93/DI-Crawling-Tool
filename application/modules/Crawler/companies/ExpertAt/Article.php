<?php

/**
 * Artikelcrawler für Expert AT family 72783
 */
class Crawler_Company_ExpertAt_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sPage->open('https://www.expert.at/export/google-merchant-center/expert-product-list.xml');

        $items = simplexml_load_string($sPage->getPage()->getResponseBody());
        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($items->channel->item as $item) {
            $namespaces = $item->getNameSpaces(true);
            $gNameSpaceElements = $item->children($namespaces['g']);
            $eArticle = new Marktjagd_Entity_Api_Article();

            try {
                $eArticle->setArticleNumber($gNameSpaceElements->id->__toString())
                ->setTitle($gNameSpaceElements->title->__toString())
                ->setText($gNameSpaceElements->description->__toString())
                ->setUrl($gNameSpaceElements->link->__toString() . '?utm_source=wogibtswas')
                ->setManufacturer($gNameSpaceElements->brand->__toString())
                ->setEan($gNameSpaceElements->gtin->__toString())
                ->setPrice(preg_replace('#€#', '', $gNameSpaceElements->price->__toString()))
                ->setImage($gNameSpaceElements->image_link->__toString());

            } catch (Exception $e) {
                $this->_logger->info('Exception: ' . $e->getMessage());
                var_dump($gNameSpaceElements);
                continue;
            }

            $cArticles->addElement($eArticle, TRUE, 'simple');
        }

        return $this->getResponse($cArticles, $companyId);
    }
}
