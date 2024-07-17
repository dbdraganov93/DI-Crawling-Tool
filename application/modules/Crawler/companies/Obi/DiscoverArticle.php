<?php

/**
 * Discover Crawler für Obi (ID: 74)
 */

class Crawler_Company_Obi_DiscoverArticle extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.csv$#', $singleRemoteFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localArticleFile, TRUE)->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($singleRow['id'])
                ->setImage($singleRow['image_link'])
                ->setUrl(preg_replace('#\?.*#', '', $singleRow['link']))
                ->setPrice(preg_replace('#\s*€#', '', $singleRow['price']))
                ->setTitle($singleRow['titel']);

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles);
    }
}
