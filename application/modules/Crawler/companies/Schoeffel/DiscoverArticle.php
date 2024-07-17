<?php

/**
 * Discover für Schoeffel (ID: 71098)
 */

class Crawler_Company_Schoeffel_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        $campaigns = [
            0 => [
                'start' => '22.11.2022',
                'end' => '10.12.2022 23:59:59'
            ],
        ];

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $cArticles = new Marktjagd_Collection_Api_Article();


        $localPath = $sFtp->connect($companyId , true);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#[^\.]+\.xlsx#', $singleFile)) {
               $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }
        $sFtp->close();

        $aData = $sPss->readFile($localBrochurePath, TRUE,)->getElement(0)->getData();

        foreach($campaigns as $campaign) {

            foreach ($aData as $product) {


                $eArticle = new Marktjagd_Entity_Api_Article();

                $eArticle->setArticleNumber($product["article_number"] . "_Disc")
                    ->setTitle($product['title'])
                    ->setText($product["text"])
                    ->setImage($product["image Link"])
                    ->setPrice($product['price'])
                    ->setStart($campaign['start'])
                    ->setEnd($campaign['end'])
                    ->setUrl($product["url"])
                    ->setVisibleStart($campaign['start']);

                $cArticles->addElement($eArticle, true, 'complex', false);

            }
        }

        return $this->getResponse($cArticles, $companyId);
    }
}