<?php

/*
 * Article Crawler für Decathlon (ID: 68079)
 */

class Crawler_Company_Decathlon_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPage = new Marktjagd_Service_Input_Page();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xls#', $singleFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aArticleData = $sPss->readFile($localArticleFile)->getElement(0)->getData();
        $aUrls = [];
        foreach ($aArticleData as $singleColumn) {
            foreach ($singleColumn as $singleField) {
                if (preg_match('#^http#', $singleField)) {
                    $aUrls[] = $singleField;
                }
            }
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aUrls as $singleUrl) {
            $sPage->open(preg_replace('#\?.+#', '', $singleUrl));
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<meta[^>]*property="(og|product:original_price):([^"]+?)"[^>]*content="([^"]+?)"#';
            if (!preg_match_all($pattern, $page, $articleInfoMatches)) {
                throw new Exception($companyId . ': unable to get any article infos: ' . $singleUrl);
            }

            $aInfos = array_combine($articleInfoMatches[2], $articleInfoMatches[3]);

            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setTitle($aInfos['title'])
                ->setImage($aInfos['image'])
                ->setUrl($singleUrl)
                ->setPrice($aInfos['amount']);

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

}
