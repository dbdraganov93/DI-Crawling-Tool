<?php

class Marktjagd_Service_Output_GoogleLia
{
    /**
     * @param $companyId
     * @param $aStoresMapped
     * @param $strInventoryFileRemote
     * @param $cArticle
     * @throws Exception
     */
    public function generateGoogleLiaFeed($companyId, $aStoresMapped, $strInventoryFileRemote, $cArticle)
    {
        try {
            $aArticles = $this->_generateArticleData($strInventoryFileRemote, $aStoresMapped);
            $cArticles = $this->_getCrawledArticleData($cArticle);

            echo date('Y-m-d H:i:s', time()) . " found " . count(array_keys($aArticles)) . " distinct articles in the inventory-feed\n";

            $localProducts = array();
            $localProductsInventory = array();
            $notInCrawledArticles = array();
            foreach (array_keys($aArticles) as $articleNumber) {
                if (!array_key_exists($articleNumber, $cArticles)) {
                    $notInCrawledArticles[] = array(
                        'itemid' => $articleNumber,
                    );
                    continue;
                }
                $localProducts[] = $cArticles[$articleNumber];
                foreach ($aArticles[$articleNumber] as $storeNumber => $stockDetails) {
                    $localProductsInventory[] = array(
                        'itemid' => $articleNumber,
                        'store code' => $storeNumber,
                        'quantity' => $stockDetails['stock'],
                        'price' => 'EUR ' . $stockDetails['price']
                    );
                }
            }

            if (!count($localProducts) || !count($localProductsInventory)) {
                throw new Exception($companyId . ': something went wrong with the amount of articles.');
            }

            echo date('Y-m-d H:i:s', time()) . " build " . count($localProducts) . " local products and " . count($localProductsInventory) . " stock infos\n";
            echo date('Y-m-d H:i:s', time()) . " deploying new feeds\n";

            $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
            $sFtp->connect();
            $sFtp->upload($this->_getCsvFromArray($localProducts), './googleLia/' . $companyId . '-local-products.csv');
            $sFtp->upload($this->_getCsvFromArray($notInCrawledArticles), './googleLia/' . $companyId . '-local-products-not-included.csv');
            $sFtp->upload($this->_getCsvFromArray($localProductsInventory, false), './googleLia/' . $companyId . '-local-products-inventory.csv');
            echo date('Y-m-d H:i:s', time()) . " DONE\n";

        } catch (Exception $e) {
            throw new Exception('Y-m-d H:i:s', time() . " SCRIPT FAILED: " . $e->getMessage() . "\n");
        }
    }

    /**
     * @param $strInventoryPathRemote
     * @param $aStoresMapped
     * @return array
     * @throws Exception
     */
    protected function _generateArticleData($strInventoryPathRemote, $aStoresMapped)
    {
        $inventoryFile = APPLICATION_PATH . '/../public/files/inventory-' . date('YmdH', time());
        if (!file_exists($inventoryFile)) {
            echo date('Y-m-d H:i:s', time()) . " downloading inventory feed\n";

            $ch = curl_init($strInventoryPathRemote);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            $result = curl_exec($ch);
            curl_close($ch);

            $fh = fopen($inventoryFile, 'w+');
            fwrite($fh, $result);
        }

        $articles = array();
        $fp = fopen($inventoryFile, 'r');
        if (!$fp) {
            throw new Exception("unable to open inventory file");
        }

        while ($cells = fgetcsv($fp, 0, ';')) {
            $articleNumber = (int)$cells[0];
            $storeNumber = (int)$cells[1];
            $stock = (int)$cells[2];
            $price = (double)$cells[3];
            if (!in_array($storeNumber, array_keys($aStoresMapped))) {
                continue;
            }
            if ($stock < 0 || $price <= 0 || $articleNumber <= 0) {
                continue;
            }
            $articles[$articleNumber][$storeNumber] = array(
                'stock' => $stock,
                'price' => $price,
            );
        }

        return $articles;
    }

    /**
     * @param $cArticle
     * @return array
     */
    protected function _getCrawledArticleData($cArticle)
    {
        $crawledArticles = array();
        foreach ($cArticle->getElements() as $article) {
            if (!$article->title) {
                continue;
            }
            $articleNumber = preg_replace('/artikel([0-9]+)/', '$1', $article->articleNumber);
            $crawledArticles[$articleNumber] = array(
                'itemid' => $articleNumber,
                'title' => $article->title,
                'description' => $article->text,
                'image_link' => $article->image,
                'condition' => 'new',
            );
        }
        return $crawledArticles;
    }

    /**
     * @param $array
     * @param bool $useFputcsv
     * @return string
     */
    protected function _getCsvFromArray($array, $useFputcsv = true)
    {
        $fp = fopen($tmpCSV = APPLICATION_PATH . '/../public/files/tmpCsv' . time() . '.csv', 'w');
        foreach ($array as $rowNo => $row) {
            if ($row == reset($array)) {
                if ($useFputcsv) {
                    fputcsv($fp, array_keys($row));
                } else {
                    fputs($fp, implode(",", array_keys($row)) . "\n");
                }
            }
            if ($useFputcsv) {
                fputcsv($fp, $row);
            } else {
                fputs($fp, implode(",", $row) . "\n"); // We don't use the fputcsv function here because we don't want to use enclosure characters for this file
            }
        }
        fclose($fp);
        return $tmpCSV;
    }
}