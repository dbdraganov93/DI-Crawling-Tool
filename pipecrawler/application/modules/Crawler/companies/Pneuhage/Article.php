<?php

/**
 * Artikel Crawler für Pneuhage (ID: 29002)
 */
class Crawler_Company_Pneuhage_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.pneuhage.de/';
        $feedUrl = $baseUrl . 'shop/media/productsfeed/offerista_[[SEASON]]_produkte.csv';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sTimes = new Marktjagd_Service_Text_Times();

        if (strtotime('now') <= strtotime('15.03.' . $sTimes->getWeeksYear())
            && strtotime('now') >= strtotime('01.09.' . ($sTimes->getWeeksYear()-1)) {
            $feedUrl = preg_replace('#\[\[SEASON\]\]#', 'winter', $feedUrl);
        } else {
            $feedUrl = preg_replace('#\[\[SEASON\]\]#', 'sommer', $feedUrl);
        }

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $localArticleFilePath = $sHttp->getRemoteFile($feedUrl, $localPath);

        $aArticle = $sExcel->readFile($localArticleFilePath, TRUE, ',')->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aArticle as $singleArticle) {
            $strText = '';
            foreach ($singleArticle as $key => $content) {
                if (!preg_match('#^BP_\d#', $key)) {
                    continue;
                }
                if (strlen($strText)) {
                    $strText .= '<br/>';
                }
                $strText .= $content;
            }
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($singleArticle['aid'])
                ->setManufacturer($singleArticle['brand'])
                ->setEan($singleArticle['ean'])
                ->setTitle(trim($singleArticle['name']))
                ->setPrice($singleArticle['price'])
                ->setUrl($singleArticle['link'])
                ->setImage($singleArticle['url_image'])
                ->setSize($singleArticle['size'])
                ->setText($strText);

            $cArticles->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
