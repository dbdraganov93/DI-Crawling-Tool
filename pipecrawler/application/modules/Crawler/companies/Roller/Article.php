<?php

/* 
 * Artikel Crawler für Roller (ID: 76)
 */

class Crawler_Company_Roller_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $remoteFileName = 'http://transport.productsup.io/ffbb43732153db194eee/557/roller_epro_marktjagd.csv';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sHttp->getRemoteFile($remoteFileName, $localPath);
        
        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#csv$#', $singleFile)) {
                $aArticles = $sExcel->readFile($localPath . $singleFile, TRUE, ',')->getElement(0)->getData();
                break;
            }
        }
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aArticles as $singleArticle)
        {
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($singleArticle['article_number'])
                    ->setArticleNumberManufacturer($singleArticle['article_number_manufacturer'])
                    ->setColor($singleArticle['color'])
                    ->setEan($singleArticle['ean'])
                    ->setImage($singleArticle['image'])
                    ->setManufacturer($singleArticle['manufacturer'])
                    ->setPrice($singleArticle['price'])
                    ->setSize($singleArticle['size'])
                    ->setSuggestedRetailPrice($singleArticle['suggested_retail_price'])
                    ->setTags($singleArticle['tags'])
                    ->setText($singleArticle['text'])
                    ->setTitle($singleArticle['title'])
                    ->setTrademark($singleArticle['trademark'])
                    ->setUrl($singleArticle['url']);
            
            if (preg_match('#kostenlos#i', $eArticle->getPrice())
                    || preg_match('#^(neu)#i', $eArticle->getUrl())
                    || preg_match('#^(neu)#i', $eArticle->getArticleNumber()))
            {
                continue;
            }
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}