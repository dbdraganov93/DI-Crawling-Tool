<?php
/**
 * Article Crawler für Möbelland (ID: 73716)
 */

class Crawler_Company_Moebelland_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $campaignParameters = [
            'campaignStart' => '09.06.2021',
            'campaignEnd' => '21.06.2021',
        ];

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sArchive = new Marktjagd_Service_Input_Archive();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        $localArticleFile = '';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xlsx?$#', $singleFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }
        $aImages = [];
        foreach ($sFtp->listFiles('./Discover_1') as $singleImage) {
            if (preg_match('#([^\.\/]+?)\.jpe?g$#', $singleImage, $nameMatch)) {
                $aImages[strtolower($nameMatch[1])] = 'ftp://crawler:0fa5fa8f351febcddedf0bbd1324a885@ftp.marktjagd.de/73716/Discover_1/' . basename($singleImage);
            }
        }

        $sFtp->close();

        if (!count($aImages)) {
            throw new Exception($companyId . ': no image archive found.');
        }

        if (!strlen($localArticleFile)) {
            throw new Exception($companyId . ': no article file found.');
        }

        $aData = $sPss->readFile($localArticleFile, TRUE)->getElement(1)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleRow) {
            if (is_null($singleRow['article_number'])) {
                break;
            }
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber(trim($singleRow['article_number']))
                ->setTitle($singleRow['title'])
                ->setText($singleRow['text'])
                ->setPrice($singleRow['price'])
                ->setImage($aImages[strtolower(preg_replace('#\.jpe?g#', '', $singleRow['image']))])
                ->setUrl($singleRow['url'])
                ->setStart($campaignParameters['campaignStart'])
                ->setEnd($campaignParameters['campaignEnd'])
                ->setVisibleStart($eArticle->getStart());

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles);
    }
}