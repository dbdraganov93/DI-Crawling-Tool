<?php

/**
 * Artikelcrawler für Ernstings Family AT (ID: 72601)
 */
class Crawler_Company_ErnstingsAt_Article extends Crawler_Generic_Company
{
    private const DATE_FORMAT = 'd.m.Y';
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $cArticles = new Marktjagd_Collection_Api_Article();

        $FTPS_URL = 'ftps://transfer.service-ehg.com/efDataFeed.xml';
        $USER_NAME = 'wogibtswas';
        $PASSWORD = 'ab2vDcRqAj?m';

        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $FTPS_URL);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_USERPWD, $USER_NAME . ':' . $PASSWORD);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
        $output = curl_exec($curlHandle);
        curl_close($curlHandle);

        $articles = new SimpleXMLElement($output);

        /** @var SimpleXMLElement $article */
        foreach ($articles->channel->item as $article) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            $namespaces = $article->getNameSpaces(true);
            // get XML items that has a colons in tag names
            $gNameSpaceElements = $article->children($namespaces['g']);

            try {
                $startDate = new DateTime();
                $endDateTime = new DateTime();
                $endDate = $endDateTime->createFromFormat('Y-m-d', (string) $gNameSpaceElements->expiration_date);

                if ($startDate > $endDate) {
                    $endDate = new DateTime();
                    $endDate->modify('+7 day');
                }

                $eArticle->setArticleNumber(ltrim($gNameSpaceElements->id, '0'))
                    ->setTitle($article->title)
                    ->setText($article->description)
                    ->setUrl($article->link)
                    ->setManufacturer($gNameSpaceElements->brand)
                    ->setEan($gNameSpaceElements->gtin)
                    ->setPrice($gNameSpaceElements->price)
                    ->setImage($gNameSpaceElements->image_link)
                    ->setColor($gNameSpaceElements->color)
                    ->setStart($startDate->format(self::DATE_FORMAT))
                    ->setVisibleStart($startDate->format(self::DATE_FORMAT))
                    ->setEnd($endDate->format(self::DATE_FORMAT));

            } catch (Exception $e) {
                $this->_logger->info('Exception: ' . $e->getMessage());
                var_dump($eArticle);
                var_dump($gNameSpaceElements);
            }

            if (floatval($article->price_old) > floatval($gNameSpaceElements->price)) {
                $eArticle->setSuggestedRetailPrice(floatval($article->price_old));
            }

            $shipping = [];
            foreach ($gNameSpaceElements->shipping as $item) {
                $shipping[] = $item->service . ': ' . $item->price . ' €';
            }
            $eArticle->setShipping(implode(', ', $shipping));

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}
