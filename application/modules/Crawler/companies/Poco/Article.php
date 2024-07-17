<?php

/**
 * Artikelcrawler für Poco (ID: 197)
 */
class Crawler_Company_Poco_Article extends Crawler_Generic_Company
{

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $url = 'https://transport.productsup.io/95d1c88539863934396d/channel/360160/marktjagd.xml';
        $cArticle = new Marktjagd_Collection_Api_Article();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($url);
        $page = $sPage->getPage()->getResponseBody();

        // XML is not escaped properly, so cannot use SimpleXMLElement
        $pattern = '#<product>\s*(.+?)\s*<\/product>#';
        if (!preg_match_all($pattern, $page, $articleMatches)) {
            throw new Exception($companyId . ': The crawler unable to get any articles/products.');
        }

        foreach ($articleMatches[1] as $xmlProduct) {
            $articleNumber = '';
            $ean = '';
            $title = '';
            $text = '';
            $price = '';
            $suggestedPrice = '';
            $url = '';
            $image = '';
            $tagsToSearch = [
                'article_number', 'ean', 'title', 'text', 'price', 'suggested_retail_price', 'url', 'image'
            ];

            foreach ($tagsToSearch as $tag) {
                $pattern = '#<' . $tag . '>\s*(.+?)\s*<\/' . $tag . '>#';
                if (!preg_match($pattern, $xmlProduct, $tagMatch)) {
                    $this->_logger->warn(
                        'The crawler was unable to get the tag ' . $tag . ' from: ' . $xmlProduct
                    );
                }

                switch ($tag) {
                    case 'article_number':
                        $articleNumber = $tagMatch[1];
                        break;
                    case 'ean':
                        $ean = $tagMatch[1];
                        break;
                    case 'title':
                        $title = $tagMatch[1];
                        break;
                    case 'text':
                        $text = $tagMatch[1];
                        break;
                    case 'price':
                        $price = $tagMatch[1];
                        break;
                    case 'suggested_retail_price':
                        $suggestedPrice = $tagMatch[1];
                        break;
                    case 'url':
                        $url = $tagMatch[1];
                        break;
                    case 'image':
                        $image = $tagMatch[1];
                        break;
                }
            }

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($articleNumber)
                ->setEan($ean)
                ->setTitle($title)
                ->setText($text)
                ->setPrice($price)
                ->setSuggestedRetailPrice($suggestedPrice)
                ->setUrl($url . '?utm_medium=brochure&utm_source=offerista&utm_campaign=angebote&utm_content=' . urlencode($title) . '_' . $articleNumber)
                ->setImage($image)
            ;

            $cArticle->addElement($eArticle);
        }

        return $this->getResponse($cArticle, $companyId);
    }
}
