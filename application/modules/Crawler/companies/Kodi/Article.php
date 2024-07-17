<?php

/*
 * Artikel Crawler für KODi Diskontläden (ID: 63)
 */

class Crawler_Company_Kodi_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://kodi.de/onmacon/';
        $searchUrl = $baseUrl . 'onmacon.csv';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sTextFormat = new Marktjagd_Service_Text_TextFormat();
        
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $localFile = $localPath . 'onmacon.csv';
        
        if (!$sHttp->getRemoteFile($searchUrl, $localPath))
        {
            throw new Exception($companyId . ': unable to get file.');
        }
        
        $data = $sExcel->readFile($localFile, TRUE, '|')->getElement(0)->getData();
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($data as $singleArticle)
        {
            foreach ($singleArticle as &$singleInfoField) {
                $singleInfoField = utf8_decode(preg_replace(array('#^\'#', '#\'$#','#\'{2}#'), array('', '', '\''), $singleInfoField));
                if (!$sTextFormat->checkValidUtf8($singleInfoField)) {
                    continue 2;
                }
            }
            
            $eArticle = new Marktjagd_Entity_Api_Article();
            
            $eArticle->setArticleNumber($singleArticle['Artikelnummer'])
                    ->setTitle($singleArticle['Titel'])
                    ->setText($singleArticle['Beschreibung'])
                    ->setUrl($singleArticle['Deeplink'])
                    ->setPrice($singleArticle['Preis'])
                    ->setSuggestedRetailPrice($singleArticle['Streichpreis'])
                    ->setEan($singleArticle['EAN'])
                    ->setShipping($singleArticle['Versandkosten'])
                    ->setSize($singleArticle['Größe'])
                    ->setColor($singleArticle['Farbe'])
                    ->setImage($singleArticle['Bildlink_large'])
                    ->setStart($singleArticle['verkaufbar ab'])
                    ->setVisibleStart($eArticle->getStart());
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
