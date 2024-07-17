<?php

/**
 * Artikel Crawler für Weltbild (ID: 28894)
 */
class Crawler_Company_Weltbild_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $file = '153386966.33379.csv';
        $aConfig = array(
            'hostname' => 'ftp.semtrack.de',
            'username' => 'ftp-33379-153386966',
            'password' => '25405863'
        );
        $sFtp = new Marktjagd_Service_Transfer_Ftp();
        $sFtp->connect($aConfig);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $localFile = $sFtp->downloadFtpToDir($file, $localPath);
                
        // Datei Zeilenweise durchlaufen und ertmal säubern, neu speichern
        $fi = fopen($localFile,'r');
        $fo = fopen($localFile . '.tmp','w+');        
        
        while(($line = fgets($fi)) !== false){                      
            $line = preg_replace('#\\\"\"#', '\"', $line);
            $line = preg_replace('#\x1A#', '', $line);           
            fputs($fo,$line);
        }
        
        fclose($fi);
        fclose($fo);
        
        $this->_logger->log('converted file save to: ' . $localFile . '.tmp', Zend_Log::INFO);
        
        $sExcel = new Marktjagd_Service_Input_PhpExcel();        
        $aArticles = $sExcel->readFile($localFile . '.tmp', true, ';')->getElement(0)->getData();
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        
        foreach ($aArticles as $singleArticle) {
            $eArticle = new Marktjagd_Entity_Api_Article();
    
            $eArticle->setArticleNumber($singleArticle['offerID'])
                    ->setTitle($singleArticle['name'])
                    ->setPrice($singleArticle['prices'])
                    ->setUrl($singleArticle['deepLink'])
                    ->setImage(preg_replace('#\?.+?$#', '', $singleArticle['imageURL']) . ',' 
                            . preg_replace('#\?.+?$#', '', $singleArticle['imageURL2']))
                    ->setEan($singleArticle['EAN'])
                    ->setManufacturer($singleArticle['brand'])
                    ->setSuggestedRetailPrice($singleArticle['oldPrices'])
                    ->setShipping($singleArticle['Versandkosten'])                    
                    ->setText(str_replace("\x07", '', $singleArticle['Kurzbeschreibung']));                    
                    
            // Stichworte aus der Kategorie erzeugen
            $words = preg_split('#(\&|\/|\s)#', $singleArticle['merchantCategory']);
            $tags = array();
            foreach ($words as $word){
                if (strlen($word) > 3 && preg_match('#[A-Z|Ü|Ö|Ä]#', $word)){
                    $tags[] = $word;
                }
            }            
            $eArticle->setTags(implode(',', array_unique($tags)));

            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}