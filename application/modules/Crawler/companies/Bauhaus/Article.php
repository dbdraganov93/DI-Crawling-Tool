<?php

/**
 * Artikel Crawler für Bauhaus (ID: 577)
 */
class Crawler_Company_Bauhaus_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $strTrackingParam = '?pk_campaign=kooperation&pk_kwd=marktjagd_20301279';
        
        $sFtp->connect($companyId);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#offers#i', $singleFile)) {
                $csvFile = $singleFile;
                break;
            }
        }
        
        $localCsvFile = $sFtp->downloadFtpToCompanyDir($csvFile, $companyId);
        $csvData = $sExcel->readFile($localCsvFile, false, ';')->getElement(0)->getData();
        
        $aKeys = array();
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($csvData as $singleArticle) {
            if (is_null($singleArticle[7])) {
                continue;
            }
            if (!count($aKeys)) {
                foreach ($singleArticle as $keyArticle) {
                    $aKeys[] = $keyArticle;
                }
                continue;
            }
            
            $aArticle = array_combine($aKeys, $singleArticle);
            if (!preg_match('#in\s*stock#i', $aArticle['Verfügbarkeit'])) {
                continue;
            }
            
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($aArticle['Artikelnummer'])
                    ->setUrl($aArticle['Link auf Detailseite'] . $strTrackingParam)
                    ->setText($aArticle['Beschreibung'] . '<br/>Kategorie: ' . $aArticle['Kategorie'])
                    ->setManufacturer($aArticle['Marke'])
                    ->setTitle($aArticle['Produktname'])
                    ->setArticleNumberManufacturer($aArticle['Hersteller-Artikelnummer'])
                    ->setEan($aArticle['EAN'])
                    ->setImage($aArticle['Link auf Produktbild'])
                    ->setShipping($aArticle['Versandkosten'])
                    ->setPrice($aArticle['Preis']);
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}