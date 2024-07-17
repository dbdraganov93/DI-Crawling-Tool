<?php

/*
 * Artikel Crawler für DM (ID: 27)
 */

class Crawler_Company_Dm_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://get.cpexp.de/';
        $searchUrl = $baseUrl . '1tDBkxICSnYzLe0YCkkCCCIeJWHxwcSKotIokl-GQvjG1eUTGVGPLjsPyt7SMxig/dm-shop_offeristade.csv';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sHttp->getRemoteFile($searchUrl, $localPath);

        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#\.csv$#', $singleFile)) {
                $articleFilePath = $localPath . $singleFile;
            }
        }

        $aArticleData = $sExcel->readFile($articleFilePath, TRUE, '|')->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aArticleData as $singleArticleData) {
            if (preg_match('#free#', $singleArticleData['price'])) {
                continue;
            }
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setPrice($singleArticleData['price'])
                ->setTitle($singleArticleData['title'])
                ->setArticleNumber($singleArticleData['arcticle_number'])
                ->setText($singleArticleData['text'] . '<br/><br/>' . $singleArticleData['tags'])
                ->setEan($singleArticleData['ean'])
                ->setSuggestedRetailPrice($singleArticleData['suggested_retail_price'])
                ->setStart($singleArticleData['start'])
                ->setEnd($singleArticleData['end'])
                ->setVisibleStart($singleArticleData['visible_start'])
                ->setVisibleEnd($singleArticleData['visible_end'])
                ->setImage(preg_replace(array('#https:\/\/dm-static-prod01\.dm-drogeriemarkt\.com\/#', '#files#'), array('https://cdn02.dm-static.com/', 'images'), $singleArticleData['image']))
                ->setStoreNumber($singleArticleData['store_number'])
                ->setDistribution($singleArticleData['distribution'])
                ->setManufacturer($singleArticleData['manufacturer'])
                ->setArticleNumberManufacturer($singleArticleData['article_number_manufacturer'])
                ->setTrademark($singleArticleData['trademark'])
                ->setColor($singleArticleData['color'])
                ->setSize($singleArticleData['size'])
                ->setAmount($singleArticleData['amount'])
                ->setShipping($singleArticleData['shipping'])
                ->setUrl($singleArticleData['deeplink'] . '?wt_mc=pdm.offerista.onlineshop.psm');

            if (preg_match('#(Vibra|Penis|Menstruation|MOQQA|Kondom|Sperm)#', $eArticle->getTitle())) {
                continue;
            }
            if (preg_match('#(Desinfektionsmittel|Toilettenpapier)#', $eArticle->getTitle())) {
                continue;
            }

            if (strtotime('now') > strtotime('24.12.' . date('Y'))
                || strtotime('now') < strtotime('01.09.' . date('Y'))) {
                if (preg_match('#(Weihnacht|Advents|Nikolaus|Lebkuch|Spekulatius|Dominosteine|Christ\w{3})#', $eArticle->getTitle())) {
                    continue;
                }
            }

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

}
