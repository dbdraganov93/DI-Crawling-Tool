<?php
/*
 * Stockard Discover Articles Crawler für DM (ID: 27)
 */

class Crawler_Company_Dm_StockardImport extends Crawler_Generic_Company
{
    protected string $csv = APPLICATION_PATH . '/../public/files/dm_stockard.csv';

    public function crawl($companyId)
    {
        //test

        echo 'checking Dm Stockard import for today' . ' ' . date('Y-m-d H:i:s');

        $sendMail = new Marktjagd_Service_Transfer_Email();
        $mApi = new Marktjagd_Service_Input_MarktjagdApi();
        $activeArticles = $mApi->findActiveArticlesByCompany($companyId);


        $newvalues = $this->getActiveDiscoverArticles($activeArticles);

        if (empty($newvalues)) {

            Zend_Debug::dump("NO NEW ARTICLES AT THIS TIME TO BE IMPORTED. CHECK STRTOTIME ()");
            die();

        } else
            $ainfos = [
                'text' => count($newvalues) . ' ' . 'new products to import from DM' . '(' . $companyId . ')' . ' at ' . date('H:i') . 'CET',
                'from' => 'di@offerista.com',
                'to' => 'de-pdm@offerista.com',
                'subject' => count($newvalues) . ' ' . 'New products imported for (DM)' . ' ' . '(' . $companyId . ')' . date("d/m/Y"),
                'attachment' => [],

            ];

        $ainfos['attachment'][] = $this->csv;

        $sendMail->sendMail($ainfos);

        return $this->getResponse($companyId);
    }

    private function getActiveDiscoverArticles(array $activeArticles): ?array
    {

        $fh = fopen($this->csv, 'w');

        $newvalues = [];
        foreach ($activeArticles as $article) {

            if (isset($article["articleNumber"])) {

                if (preg_match('#_Disc#', $article["articleNumber"])) {

                    if (strtotime('now - 3 hour') < strtotime($article['created'])) {

                        $newvalues[] = $article;

                        echo "NEW ID FOUND" . ' ' . $article["articleNumber"] . PHP_EOL;

                        fputcsv($fh, $article, ",");

                    }

                }
            }

        }
        fclose($fh);

        return $newvalues;
    }


}
