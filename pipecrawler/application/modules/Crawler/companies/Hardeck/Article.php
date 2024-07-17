<?php

/* 
 * Artikel - Crawler für Hardeck (ID: 69067)
 */

class Crawler_Company_Hardeck_Article extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sFtp->connect($companyId);
        $thisWeek = $sTimes->getWeekNr();
        $nextWeek = $sTimes->getWeekNr('next');

        $pattern = '#kw(' . $thisWeek . '|' . $nextWeek . ')#';
        
        foreach ($sFtp->listFiles() as $singleFile)
        {
            if (preg_match($pattern, $singleFile, $weekMatch))
            {
                $articleFile = $sFtp->downloadFtpToCompanyDir($singleFile, $companyId);
                break;
            }
        }
        
        $aData = $sExcel->readFile($articleFile, true, ';')->getElement(0)->getData();
        
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleData)
        {
            $eArticle = new Marktjagd_Entity_Api_Article();
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $singleData['Bild-Url']);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            if ($info['http_code'] != 200) {
                $this->_logger->info($companyId . ': not a valid image: ' . $singleData['Bild-Url']);
                continue;
            }
            
            $eArticle->setArticleNumber($singleData['Artikelnummer'])
                    ->setTitle($singleData['Produktname'])
                    ->setText($singleData['Beschreibung'])
                    ->setPrice($singleData['Preis'])
                    ->setUrl($singleData['URL'])
                    ->setImage($singleData['Bild-Url'])
                    ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear(), $weekMatch[1], 'Mi'))
                ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear(), date('W', strtotime($eArticle->getStart() . '+1week')), 'Di'))
                ->setVisibleStart($eArticle->getStart());
            
        $cArticles->addElement($eArticle);

        }
        return $this->getResponse($cArticles, $companyId);
    }
}