<?php

/**
 * Artikelcrawler für Euronics (ID: 86)
 */
class Crawler_Company_Euronics_Article extends Crawler_Generic_Company
{

    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {

        $sHttp = new Marktjagd_Service_Transfer_Http();
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        ini_set('memory_limit', '1G');

        $fh = fopen($localPath . 'euronicsmarktplatz_marktjagdde.csv', 'w');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://get.cpexp.de/Qg99f8yCUlOcMD-sBZXbtSWtCfdgqhobpyHhYrhkSpiIoICZj1-T8JWhzmzQrlUu/euronicsmarktplatz_marktjagdde.csv');
        curl_setopt($ch,CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_exec($ch);
        curl_close($ch);
        fclose($fh);

        $localFile = $localPath . 'euronicsmarktplatz_marktjagdde.csv';
        $header = array();
        $aArticleData = array();
        $aFilter = array();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $aStoreNumbers = array();
        $aStores = $sApi->findAllStoresForCompany($companyId);
        foreach ($aStores as $singleStore)
        {
            $aStoreNumbers[] = $singleStore['number'];
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        $fh = fopen($localFile, 'r');
        $lineNo = 0;
        while (($line = fgetcsv($fh, 0, ';')) != FALSE)
        {
            if (!count($header))
            {
                $header = $line;
                continue;
            }
            $lineNo ++;
            $aArticleData = array_combine($header, $line);
            if (in_array($aArticleData['store_number'], $aStoreNumbers))
            {
                $aFilter[$aArticleData['store_number']][$aArticleData['article_number']] = (float) $aArticleData['price'];
            }
        }

        $aFiltered = array();
        foreach ($aFilter as $singleFilterKey => $singleFilterValue)
        {
//            asort($singleFilterValue, SORT_NUMERIC);
            $tmp = array_chunk($singleFilterValue, 50, TRUE);

            foreach ($tmp[0] as $key => $value)
            {
                $aFiltered[$key] = $value;
            }
        }
        rewind($fh);

        while (($line = fgetcsv($fh, 0, ';')) != FALSE)
        {
            if (!count($header))
            {
                $header = $line;
                continue;
            }

            $aArticleData = array_combine($header, $line);

            if (!$aFiltered[$aArticleData['article_number']])
            {
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setTitle($aArticleData['title'])
                    ->setPrice($aArticleData['price'])
                    ->setText($aArticleData['text'])
                    ->setEan($aArticleData['ean'])
                    ->setManufacturer($aArticleData['manufacturer'])
                    ->setArticleNumberManufacturer($aArticleData['article_number_manufacturer'])
                    ->setSuggestedRetailPrice($aArticleData['suggested_retail_price'])
                    ->setTrademark($aArticleData['trademark'])
                    ->setTags($aArticleData['tags'])
                    ->setColor($aArticleData['color'])
                    ->setAmount($aArticleData['amount'])
                    ->setStart($aArticleData['start'])
                    ->setEnd($aArticleData['end'])
                    ->setVisibleStart($aArticleData['visible_start'])
                    ->setVisibleEnd($aArticleData['visible_end'])
                    ->setUrl($aArticleData['url'])
                    ->setShipping($aArticleData['shipping'])
                    ->setImage($aArticleData['image'])
                    ->setStoreNumber($aArticleData['store_number'])
                    ->setDistribution($aArticleData['distribution'])
                    ->setArticleNumber($eArticle->getHash());

            if (preg_match('#(2c7d7e3a0c962692ad36c983316cc978|3ebced09e5c93597b790fbae0e763563|2abb49d648b6add10f6b27d09f2e3566)#', $eArticle->getArticleNumber()))
            {
                continue;
            }
            $cArticles->addElement($eArticle, true);
        }
        fclose($fh);
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }

    protected function _gzdecode($data)
    {
        $g = tempnam('/tmp', 'ff');
        file_put_contents($g, $data);
        ob_start();
        readgzfile($g);
        $d = ob_get_clean();
        unlink($g);
        return $d;
    }

}
