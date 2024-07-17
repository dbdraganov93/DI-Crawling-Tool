<?php

/**
 * Artikelcrawler für XXXLutz (ID: 80)
 */
class Crawler_Company_XxxlShop_Article extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        ini_set('memory_limit', '2G');
        $baseUrl = 'https://transport.productsup.io/';
        $fileUrl = $baseUrl . 'a8d2ec18e135c5b4dfc1/channel/271021/Bonial_mkt_4.csv';
        $filename = 'Bonial_mkt_21.csv';

        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sFtp->connect($companyId);
        $list = $sFtp->listFiles();
        $localArticleFile = $sFtp->downloadFtpToDir($list[0], $localPath);
//        var_dump($localArticleFile);die;
//        $localArticleFile = $sHttp->getRemoteFile($fileUrl, $localPath);

        $aData = $sPss->readFile($localArticleFile, TRUE, ';')->getElement(0)->getData();
//        var_dump($aData);die;


        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($singleRow['Artikelnummer'])
                ->setTitle($singleRow['Titel'])
//                ->setText(preg_replace('#[^\w\d\s\.,\!\?:äöüß]#i', '', $singleRow['Beschreibung']) . '<br/>' . $singleRow['Kategorie Ebene 0'])
                ->setText( $singleRow['Beschreibung'] . '<br/>' . $singleRow['Kategorie Ebene 0'])
                ->setPrice($singleRow['Preis'])
                ->setManufacturer($singleRow['Marke'])
                ->setUrl($singleRow['Deeplink-bonial'] . '?utm_source=offerista&utm_medium=brochure&utm_campaign=2022-01-17—2022-01-31_lde121q&utm_content=brochure')
                ->setEan($singleRow['EAN_Code'])
//                ->setImage(implode(',',[$singleRow['Bild_1'], $singleRow['Bild_2'], $singleRow['Bild_3'], $singleRow['Bild_4']]))
                ->setImage($singleRow['Bild_1'])
                ->setSuggestedRetailPrice($singleRow['Streichpreis'])
                ->setStart('01.05.2022')
                ->setEnd('02.05.2022')
                ->setVisibleStart('01.05.2022');

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

}
