<?php

/**
 * Artikel Crawler für Jawoll (ID: 29087)
 */
class Crawler_Company_Jawoll_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect($companyId);
        $fileNameCsv = 'marktjagd-jawoll.csv';
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $localFileName = $sFtp->downloadFtpToDir($fileNameCsv, $localPath);

        $cArticles = new Marktjagd_Collection_Api_Article();

        $sCsvIn = new Marktjagd_Service_Input_Csv();
        $delimiter = $sCsvIn->findDelimiter($localFileName);
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $worksheet = $sExcel->readFile($localFileName, true, $delimiter);
        $worksheet = $worksheet->getElement(0);
        /* @var $worksheet Marktjagd_Entity_PhpExcel_Worksheet */
        $lines = $worksheet->getData();

        foreach ($lines as $line) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setTitle($line["title"])
                    ->setPrice($line["price"])
                    ->setText(preg_replace(array('#\{[^\}]+?\}#', '#\s*\?\s*#'), array('', '<br/>'), $line["description"])
                        . '<br><br>Dieses Angebot gilt in unserem Online Shop.<br><br>'
                        . 'Keine Verfügbarkeit in der Filiale garantiert.<br><br>'
                        . 'Bitte erfragen Sie die Verfügbarkeit in Ihrem Jawoll Markt')
                    ->setShipping($line["shipping_costs"])
                    ->setImage($line["img_url"])
                    ->setUrl($line["deeplink1"])
                    ->setEan($line["ean"]);
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}