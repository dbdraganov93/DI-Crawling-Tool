<?php

/**
 * Article Crawler for Hornbach AT (ID: 72718)
 */

class Crawler_Company_HornbachAt_Article extends Crawler_Generic_Company {

    public function crawl($companyId)
    {
        $feedUrl = 'https://get.cpexp.de/4sQuVsBcipriqzfqz66k-MfMEmcJhXoVaVKDaqI1-R0pGlH0RjZ9qz3Me0qJgbel/productbackboneat_wogibtswasatde.csv';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        ini_set('memory_limit', '6G');

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $localFile = $sHttp->getRemoteFile($feedUrl, $localPath);

        $aData = $sPss->readFile($localFile, TRUE, '|')->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setTitle($singleRow['Produktname'])
            ->setPrice($singleRow['Preis'])
            ->setImage($singleRow['Bild-Link'])
            ->setText($singleRow['Produktbeschreibung'])
            ->setUrl($singleRow['Deeplink'])
            ->setArticleNumber($singleRow['Artikelnummer']);

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles);
    }
}