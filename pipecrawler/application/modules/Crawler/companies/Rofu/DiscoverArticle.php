<?php

/**
* Discover für Rofu (ID: 28773)
*/

class Crawler_Company_Rofu_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # Upload the article csv file onto our FTP server (folder 'Discover')   #
        #                                                                       #
        # adjust articleFile                                                    #
        #########################################################################

        $articleFile = "2021-11/Feed_Cyber_Monday_2021.csv";

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localFolder = $sFtp->connect($companyId, TRUE);
        $localArticleFile = $sFtp->downloadFtpToDir('./' . $articleFile , $localFolder);

        $aData = $sPss->readFile($localArticleFile, TRUE, '|')->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();

        $string = '665_665_98';

        foreach ($aData as $singleRow) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber('DISCOVER_' . $singleRow['artikelnummer'])
                ->setTitle($singleRow['titel'])
                ->setText($singleRow['beschreibung'])
                ->setPrice($singleRow['preis'])
                ->setManufacturer($singleRow['hersteller'])
                ->setImage(str_replace('540_340_80',$string, $singleRow['bild']))
                ->setUrl($singleRow['link'])
                ->setTrademark($singleRow['trademark']);


            $cArticles->addElement($eArticle,TRUE, 'complex', FALSE);

        }

        return $this->getResponse($cArticles, $companyId);
    }
}