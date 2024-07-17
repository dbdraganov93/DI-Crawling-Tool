<?php

/* 
 * Artikel Crawler für Center Shop (ID: 69971)
 */

class Crawler_Company_CenterShop_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        
        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        
        $aImageFiles = array();
        $pattern = '#(\d{8,13})#';
        foreach ($sFtp->listFiles('./articleImages') as $singleImage) {
            if (preg_match($pattern, $singleImage, $eanMatch)) {
                $aImageFiles[$eanMatch[1]] = $sFtp->downloadFtpToDir($singleImage, $localPath);
            }
        }
                
        $pattern = '#Artikel-Excel-Bonial\.xlsx#';        
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match($pattern, $singleFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }
        
        $articleData = $sExcel->readFile($localArticleFile, TRUE)->getElement(0)->getData();
        
        $countSkipped = 0;
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($articleData as $singleArticleData) {
            if (!strlen($singleArticleData['article'])) {
                continue;
            }
            $visibleStart = PHPExcel_Style_NumberFormat::toFormattedString($singleArticleData['visible_start'], 'd.M.Y');
            $visibleEnd = PHPExcel_Style_NumberFormat::toFormattedString($singleArticleData['visible_end'], 'd.M.Y');
            
            $eArticle = new Marktjagd_Entity_Api_Article();
            
            $eArticle->setTitle($singleArticleData['article'])
                    ->setEan($singleArticleData['ean'])
                    ->setText($singleArticleData['text'])
                    ->setPrice($singleArticleData['price'])
                    ->setUrl($singleArticleData['url'])
                    ->setVisibleStart($visibleStart)
                    ->setVisibleEnd($visibleEnd);
            
            if (array_key_exists($singleArticleData['ean'], $aImageFiles)) {
                $eArticle->setImage($sFtp->generatePublicFtpUrl($aImageFiles[$singleArticleData['ean']]));
            }
            
            if (!strlen($eArticle->getImage())) {
                $countSkipped++;
                continue;
            }
            
            $cArticles->addElement($eArticle);
        }
        
        $this->_logger->info($companyId . ': ' . $countSkipped . ' articles skipped because of missing images.');
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}