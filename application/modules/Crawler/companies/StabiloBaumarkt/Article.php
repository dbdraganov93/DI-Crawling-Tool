<?php

/* 
 * Artikel Crawler für Stabilo Fachmarkt (ID: 69917)
 */

class Crawler_Company_StabiloBaumarkt_Article extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.stabilo-fachmarkt.de/';
        $searchUrl = $baseUrl . 'plenty/api/itemShopbotExport.php?pyk=py_1b504a0c174e65fc9ac710a4e9881265&eid=9';
        $data = '';
        
        $useFTP = false;
                
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();                
        
        if ($useFTP)
        {
            $sFtp->connect($companyId);
            $localDirectory = $sFtp->generateLocalDownloadFolder($companyId);
            foreach ($sFtp->listFiles() as $singleFile) {
                if (preg_match('#' . date('W') .'#', $singleFile)) {
                    $localFile = $sFtp->downloadFtpToDir($singleFile, $localDirectory);
                }
            }      
            $data = file_get_contents($localFile);
        }
        else
        {
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $searchUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $data = curl_exec($ch);
            curl_close($ch);
        }

	$aDatasets = preg_split('#\n#', $data);
        $aHeader = array();
        $aData = array();
        
        foreach ($aDatasets as $singleDataset)
        {
            if (!strlen($singleDataset) && count($aData))
            {
                break;
            }
            $aFields = preg_split('#\t#', preg_replace('#\"#', '', html_entity_decode($singleDataset)));
            if (!count($aHeader))
            {
                $aHeader = $aFields;
                continue;
            }
            foreach ($aFields as &$singleField)
            {
                $singleField = preg_replace(array('#\&szlig\;#', '#\&amp\;#', '#nbsp;#'), array('ß', '&', ''), $singleField);
            }
            
            $aData[] = array_combine($aHeader, $aFields);
            
        }
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleData)
        {
            if (preg_match('#\_#', $singleData['id']))
            {
                continue;
            }
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($singleData['id'])
                    ->setTitle($singleData['title'])
                    ->setText($singleData['beschreibung'])
                    ->setUrl($singleData['link'])
                    ->setImage($singleData['bildlink'])
                    ->setPrice($singleData['preis'])
                    ->setArticleNumberManufacturer($singleData['mpn'])
                    ->setEan($singleData['ean'])
                    ->setColor($singleData['farbe'])
                    ->setSize($singleData['größe']);
                        
            $startDate = strtotime('next wednesday', strtotime('last monday', strtotime('tomorrow')));
            $endDate = strtotime("+13 days", $startDate);            
            
            $eArticle->setStart(date("d.m.Y", $startDate))
                    ->setVisibleStart(date("d.m.Y", $startDate))
                    ->setEnd(date("d.m.Y", $endDate));
            
            if (strlen($singleData['sonderangebotspreis']))
            {
                $eArticle->setPrice($singleData['sonderangebotspreis'])
                        ->setSuggestedRetailPrice($singleData['preis']);
            }            
            
            $cArticles->addElement($eArticle);
            if (count($cArticles->getElements()) == 150) {
                break;
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}