<?php

/**
 * Store Crawler für Netto Supermarkt (ID: 73)
 */
class Crawler_Company_NettoSupermarkt_NettoFTPArticle extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();        
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $cArticles = new Marktjagd_Collection_Api_Article();
               
        $sFtp->connect($companyId);
       
        $localArticleFile = $sFtp->downloadFtpToCompanyDir('Laekker_NETTO_Artikel.xlsx', $companyId);
       
        $articleData = $sExcel->readFile($localArticleFile, true)->getElement(0)->getData();        
        $cArticles = new Marktjagd_Collection_Api_Article();
        
        foreach ($articleData as $singleArticle) {                        
            $eArticle = new Marktjagd_Entity_Api_Article();
            
            $eArticle->setTitle($singleArticle['title'])
                    ->setPrice($singleArticle['price'])
                    ->setText($singleArticle['text'])
                    ->setEan($singleArticle['ean'])
                    ->setManufacturer($singleArticle['manufacturer'])
                    ->setArticleNumberManufacturer($singleArticle['article_number_manufacturer'])
                    ->setSuggestedRetailPrice($singleArticle['suggested_retail_price'])
                    ->setTrademark($singleArticle['trademark'])
                    ->setTags($singleArticle['tags'])
                    ->setColor($singleArticle['color'])
                    ->setSize($singleArticle['size'])
                    ->setAmount($singleArticle['amount'])
                    ->setStart($singleArticle['start'])
                    ->setEnd($singleArticle['end'])
                    ->setVisibleStart($singleArticle['visible_start'])
                    ->setVisibleEnd($singleArticle['visible_end'])
                    ->setUrl($singleArticle['url'])
                    ->setShipping($singleArticle['shipping'])
                    ->setImage($singleArticle['image'])
                    ->setStoreNumber($singleArticle['store_number'])
                    ->setDistribution($singleArticle['distribution']);                    
                        
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
