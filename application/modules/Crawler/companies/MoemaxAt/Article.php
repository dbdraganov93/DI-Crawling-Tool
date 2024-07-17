<?php

/**
 * Artikelcrawler für Moemax AT (ID: 72787)
 */
class Crawler_Company_MoemaxAt_Article extends Crawler_Generic_Company
{
    protected $_companyId;

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $this->_companyId = $companyId;
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, 'https://transport.productsup.io/d48eb6c41c2476185e77/channel/592184/moemax_at_oth_wogibtswas.csv');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
        $output = curl_exec($curlHandle);
        curl_close($curlHandle);

        $articleMap = $this->parseCsv($output);
        if ($articleMap == false) {
            throw new Exception($companyId . ': Article crawler unsuccessful.');
        }
        $this->_logger->info($companyId . ': CSV file parsed successfully');

        $size = sizeof($articleMap);
        $this->_logger->info($companyId . ': ' . $size . ' articles to be mapped.');

        $cArticles = new Marktjagd_Collection_Api_Article();
        $aArticles = [];
        $utm = '?utm_source=wogibtswas.at&utm_medium=coop&utm_campaign=productlisting';
        foreach ($articleMap as $temp) {
            $article = str_replace(chr(0x7F), '', $temp);
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber(ltrim(preg_replace('#moemax.at_#', '', $article['ArtikelID']), '0'))
                ->setTitle($article['Artikelbezeichnung'])
                ->setText($article['Beschreibung'])
                ->setPrice($article['Preis'])
                ->setUrl($article['Deeplink'] . $utm)
                ->setEan($article['EAN_Code'])
                ->setImage($article['bild_groß']);

            if (floatval($article['Preis alt']) > floatval($article['Preis'])) {
                $eArticle->setSuggestedRetailPrice($article['Preis alt']);
            }

            if (preg_match('#http:#', $eArticle->getUrl())) {
                $eArticle->setUrl(preg_replace('#http#', 'https', $eArticle->getUrl()));
            }


            if (!array_key_exists($eArticle->getUrl(), $aArticles)) {
                $aArticles[$eArticle->getUrl()] = $eArticle;
            } elseif ($eArticle->getArticleNumber() > $aArticles[$eArticle->getUrl()]->getArticleNumber()) {
                $aArticles[$eArticle->getUrl()] = $eArticle;
            }

        }

        foreach ($aArticles as $singleArticle) {
            $cArticles->addElement($singleArticle, TRUE, 'complex', FALSE);
        }


        return $this->getResponse($cArticles, $companyId);
    }

    /**
     * This method consumes an input string and parses the csv data.
     * The content will get converted to a list of maps and the header row
     * of the original csv file is used for the map-keys.
     * @param string $filePath
     * @return array
     * @throws Exception
     */

    protected function parseCsv($inputString)
    {
        try {
            $rows = array_map(function ($v) {
                return str_getcsv($v, ",");
            }, explode(PHP_EOL, $inputString));
            $header = array_shift($rows);
            $csv = [];
            foreach ($rows as $row) {
                $csv[] = array_combine($header, $row);
            }
        } catch (Exception $e) {
            $this->_logger->info($this->_companyId . ': Error during csv parsing');
            return false;
        }
        return $csv;
    }
}
