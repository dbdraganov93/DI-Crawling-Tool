<?php

/**
 * Article Crawler for Saturn AT (ID: 73047)
 */

class Crawler_Company_SaturnAt_Article extends Crawler_Generic_Company {

    public function crawl($companyId)
    {
        $baseUrl = 'https://data-mmat-feeder.performance-plan.net/';
        $searchUrl = $baseUrl . 'at-sa-mdf.wogibtswas.processed.csv';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPSs = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $localArticleFile = $sHttp->getRemoteFile($searchUrl, $localPath);

        $aData = $sPSs->readFile($localArticleFile, TRUE, ';')->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($singleRow['Produkt-ID'])
                ->setTitle($singleRow['Produkt-Titel'])
                ->setText($singleRow['Produkt-Beschreibung'])
                ->setImage($singleRow['Bild-Link'])
                ->setPrice($singleRow['Preis'])
                ->setUrl($singleRow['Deeplink'])
                ->setEan($singleRow['EAN'])
                ->setManufacturer($singleRow['Manufacturer']);

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}
