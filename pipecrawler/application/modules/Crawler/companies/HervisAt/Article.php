<?php

/**
 * Artikelcrawler für Hervis AT 73246
 */

class Crawler_Company_HervisAt_Article extends Crawler_Generic_Company
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
        $sFtp = new Marktjagd_Service_Transfer_Ftp();
        $ftpConfig = array(
            'hostname' => 'ftp.semtrack.de',
            'username' => 'ftp-11246-153387112',
            'password' => '4595b3a3'
        );

        $ret = $sFtp->connect($ftpConfig);
        if ($ret == FALSE) {
            throw new Exception($companyId . ': unable to connect to FTP.');
        }
        $this->_logger->info($companyId . ': Connected to remote FTP');

        $filePath = $sFtp->downloadFtpToCompanyDir('153387112.11246.csv', $companyId);
        if ($filePath == false) {
            throw new Exception($companyId . ': Unable to download remote file (153387112.11246.csv) to local ftp.');
        }
        $this->_logger->info($companyId . ': Obtained CSV file');

        $articleMap = $this->parseCsv($filePath);
        if ($articleMap == false) {
            throw new Exception($companyId . ': Article crawler unsuccessful.');
        }
        $this->_logger->info($companyId . ': CSV file parsed successfully');

        $size = sizeof($articleMap);
        $this->_logger->info($companyId . ': ' . $size . ' articles to be mapped.');

        $cArticles = new Marktjagd_Collection_Api_Article();
        $aArticles = [];
        foreach ($articleMap as $temp) {
            $article = str_replace(chr(0x7F), '', $temp);

            $eArticle = new Marktjagd_Entity_Api_Article();
            try {
                $eArticle->setArticleNumber(ltrim($article['ArtikelID'], '0'))
                    ->setTitle($article['Artikelbezeichnung'])
                    ->setText($article['Beschreibung'])
                    ->setPrice($article['Preis'])
                    ->setManufacturer($article['Hersteller'])
                    ->setUrl($article['Deeplink'])
                    ->setEan($article['EAN_Code'])
                    ->setImage($article['bild_gross'])
                    ->setSuggestedRetailPrice($article['PRICE_LEVEL_ORIGINAL']);

                if (floatval($article['PRICE_LEVEL_ORIGINAL']) > floatval($article['Preis'])) {
                    $eArticle->setSuggestedRetailPrice($article['PRICE_LEVEL_ORIGINAL']);
                } else {
                    continue;
                }
            } catch (Exception $e) {
                $this->_logger->warn('Exception: ' . $e->getMessage());
                var_dump($article);
                var_dump($eArticle);
                continue;
            }

            if (!array_key_exists($eArticle->getUrl(), $aArticles)) {
                $aArticles[$eArticle->getUrl()] = $eArticle;
            } elseif ($eArticle->getArticleNumber() > $aArticles[$eArticle->getUrl()]->getArticleNumber()) {
                $aArticles[$eArticle->getUrl()] = $eArticle;
            }
        }

        foreach ($aArticles as $singleArticle) {
            $singleArticle->setArticleNumber(substr(md5($singleArticle->getUrl()), 0, 20));
            $cArticles->addElement($singleArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

    /**
     * This method consumes a file path and parses the corresponding csv file.
     * The content will get converted to a list of maps and the header row
     * of the original csv file is used for the map-keys.
     * @param string $filePath
     * @return array
     * @throws Exception
     */
    protected function parseCsv($filePath)
    {
        try {
            $rows = array_map(function ($v) {
                return str_getcsv($v, ";");
            }, file($filePath));
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
