<?php

/**
 * Artikelcrawler für Moebelix AT family 73091
 */
class Crawler_Company_MoebelixAt_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, 'https://transport.productsup.io/9d258607449a963d768c/channel/566983/moebelix_at_oth_wogibtswas.csv');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
        $output = curl_exec($curlHandle);
        curl_close($curlHandle);

        $articleMap = $this->parseCsv($output, $companyId);
        if ($articleMap == false) {
            throw new Exception($companyId . ': Article crawler unsuccessful.');
        }
        $this->_logger->info($companyId . ': CSV file parsed successfully');

        $size = sizeof($articleMap);
        $this->_logger->info($companyId . ': ' . $size . ' articles to be mapped.');

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($articleMap as $article) {
                        $eArticle = new Marktjagd_Entity_Api_Article();
            try {
                $images = implode(', ', array_filter([$article['image_link'], $article['bild2'], $article['bild3'], $article['bild4'], $article['bild5'], $article['bild6'], $article['bild7']]));
                $eArticle->setArticleNumber(ltrim(str_replace('moebelix.at_', '', $article['id']), '0'))
                    ->setTitle($article['title'])
                    ->setText($article['description'])
                    ->setColor($article['color'])
                    ->setShipping($article['shipping'])
                    ->setManufacturer(str_replace(['(', ')'], '', $article['brand']))
                    ->setUrl($article['link'])
                    ->setEan($article['gtin'])
                    ->setSize($article['size'])
                    ->setImage($images)
                    ->setTags($article['product_type']);
                $eArticle->addText($article['model text 1']);
                $eArticle->addText($article['model text 2']);
                $eArticle->addText($article['model text 3']);
                $eArticle->addText($article['besonderheit']);

                if (strlen($article['title']) > 250) {
                    $title = substr($article['title'], 0, 246) . '...';
                    $eArticle->setTitle($title);
                    continue;
                }

                $this->calculatePrices($article, $eArticle);

            } catch(Exception $e) {
                $this->_logger->warn('Exception: ' . $e->getMessage());
                continue;
            }
            $ret = $cArticles->addElement($eArticle, TRUE, 'complex', FALSE);
            if ($ret != true) {
                $this->_logger->warn('Error adding article: ' . $eArticle->getArticleNumber . ', ' . $eArticle->getTitle);
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }

    /**
     * This method consumes an input string and parses the csv data.
     * The content will get converted to a list of maps and the header row
     * of the original csv file is used for the map-keys.
     * @param string $filePath
     * @return array|bool
     * @throws Exception
     */
    protected function parseCsv($inputString, $companyId) {
        try {
            $rows = array_map(function($v){return str_getcsv($v, "|");}, explode(PHP_EOL, $inputString));
            $header = array_shift($rows);
            $csv    = [];
            foreach($rows as $row) {
                $csv[] = array_combine($header, $row);
            }
        } catch (Exception $e) {
            $this->_logger->info($companyId . ': Error during csv parsing');
            return false;
        }
        return $csv;
    }

    /**
    * This methods checks and compares the sale-price and normal price
    * sets the article information accordingly.
    * @param map $article
    * @param Marktjagd_Entity_Api_Article &$eArticle
    */
    protected function calculatePrices($article, &$eArticle) {
        if (!empty($article['sale_price'])) {
            if (floatval($article['sale_price']) < floatval($article['price'])) {
                $eArticle->setPrice($article['sale_price']);
                $eArticle->setSuggestedRetailPrice($article['price']);
            } else {
                $eArticle->setPrice($article['price']);
            }
        } else {
            $eArticle->setPrice($article['price']);
        }
    }
}
