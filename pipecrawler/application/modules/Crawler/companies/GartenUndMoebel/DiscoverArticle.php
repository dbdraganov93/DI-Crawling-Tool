<?php

/**
 * Article crawler for Garten und Möbel (ID: 90220)
 */

class Crawler_Company_GartenUndMoebel_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_Ftp();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $aCredentials = [
            'hostname' => 'ftp.semtrack.de',
            'username' => 'ftp-16792-153391158',
            'password' => '865cf508'
        ];

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $sFtp->connect($aCredentials);

        $localArticleFile = '';
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#gum-offerista-prospect\.csv$#', $singleRemoteFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                break;
            }
        }
        $sFtp->close();

        $aData = $sPss->readFile($localArticleFile, TRUE, ';')->getElement(0)->getData();
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleRow) {

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($singleRow['articleNumber'] . '_Disc_' . date('m_Y') . '_2')
                ->setTitle(preg_replace('#\s{2,}#', ' ', $singleRow['title']))
                ->setImage($singleRow['image1'])
                ->setText($singleRow['text'])
                ->setUrl($singleRow['url'])
                ->setPrice($singleRow['price'])
                ->setStart('10.05.2024')
                ->setEnd('23.05.2024')
                ->setVisibleStart($eArticle->getStart());

            if ($singleRow['suggestedRetailPrice']) {
                $eArticle->setSuggestedRetailPrice($singleRow['suggestedRetailPrice']);
            }

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles);
    }
}