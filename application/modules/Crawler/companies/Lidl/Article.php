<?php

/**
 * Artikel Crawler für Lidl (ID: 28)
 */
class Crawler_Company_Lidl_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sFtp->connect($companyId);
        $sFtp->changedir('1_Articles');

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        foreach ($sFtp->listFiles() as $singleFile) {
            $this->_logger->info($singleFile);
            $localFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
        }

        if(!$localFile) {
            $this->_logger->warn("no article CSV file found.");
        }

        $aCsvFile = $sExcel->readFile($localFile, true, ';')->getElement(0);
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aCsvFile->getData() as $singleArticle) {
            if (!preg_match('#ja#i', $singleArticle['Verfügbarkeit'])
                || !strlen($singleArticle['wt_start'])
                || count($cArticles->getElements()) >= 100
            ) {
                continue;
            }
            
            $strImages = $singleArticle['Produktbild-URL'];
            if (strlen($singleArticle['Alternative Bild-URL 1'])) {
                if (strlen($strImages)) {
                    $strImages .= ',';
                }
                $strImages .= $singleArticle['Alternative Bild-URL 1'];
            }
            if (strlen($singleArticle['Alternative Bild-URL 2'])) {
                if (strlen($strImages)) {
                    $strImages .= ',';
                }
                $strImages .= $singleArticle['Alternative Bild-URL 2'];
            }

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($singleArticle['Eindeutige Händler-SKU'])
                    ->setTitle($singleArticle['produktname'])
                    ->setPrice($singleArticle['preis'])
                    ->setUrl($singleArticle['produkt-url'])
                    ->setEan($singleArticle['EAN'])
                    ->setText('Kategorie: ' . $singleArticle['kategoriename'] . '<br/><br/>' . strip_tags($singleArticle['produktbeschreibung']))
                    ->setShipping($singleArticle['versandkosten'])
                    ->setColor($singleArticle['farbe'])
                    ->setSize($singleArticle['größe'])
                    ->setManufacturer($singleArticle['hersteller'])
                    ->setImage(preg_replace('#dac\.#', 'www.', $strImages))
                    ->setVisibleStart($singleArticle['wt_start'])
                    ->setStart($singleArticle['wt_titel_extension'] . $sTimes->getWeeksYear())
                    ->setEnd($singleArticle['wt_end']);
            
            if (!strlen($singleArticle['wt_titel_extension'])) {
                $eArticle->setStart($singleArticle['wt_start']);
            }
            
            if (preg_match('#^([0-9\.\,]+)$#', $eArticle->getShipping())) {
                $eArticle->setShipping($eArticle->getShipping() . ' €');
            }
            $todayDate = new DateTime();
            $endDate = new DateTime($eArticle->getEnd());
                if (($todayDate->diff($endDate)->y) > 1) {
                    $eArticle->setStart(NULL)
                            ->setEnd(NULL);
                }
            $cArticles->addElement($eArticle);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}