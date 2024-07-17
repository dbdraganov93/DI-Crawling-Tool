<?php
/**
 * Artikelcrawler für Reifen.com (ID: 28940)
 */
class Crawler_Company_ReifenCom_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $geraeuschdbURL = 'http://www.reifen.com/images/EUTyreLabel/geraeuchdb_[VAL].png';
        $geraeuschklasseURL = 'http://www.reifen.com/images/EUTyreLabel/geraeuchklasse_[VAL].png';
        $nasslaufEigenschaftenURL = 'http://www.reifen.com/images/EUTyreLabel/NasslaufEigenschaften_[VAL].png';
        $rollwiederstandURL = 'http://www.reifen.com/images/EUTyreLabel/Rollwiederstand_[VAL].png';
        
        $timeSummerStart = date('U', strtotime('13.02.' . date('Y', strtotime('now'))));
        $timeSummerEnd = date('U', strtotime('31.08.' . date('Y', strtotime('now'))));
        $timeNow = date('U', strtotime('now'));

        if ((int)$timeNow > (int)$timeSummerStart
                && (int)$timeNow < (int)$timeSummerEnd) {
            $seasonPattern = '#(PKW\-Sommer|Motorrad|Moped|Roller)#i';
        } else {
            $seasonPattern = '#(PKW\-Winter|PKW\-Ganzjahr)#i';
        }
        
        $sCharset = new Marktjagd_Service_Text_TextFormat();

        $sDownload = new Marktjagd_Service_Transfer_Download();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadPath = $sHttp->generateLocalDownloadFolder($companyId);
        $downloadPathFile = $sDownload->downloadByUrl(
            'http://media.reifen.com/fileadmin/files/RC-Artikellisten/ArticleList_Marktjagd.csv',
            $downloadPath);

        $sCsv = new Marktjagd_Service_Input_Csv();
        $delimiter = $sCsv->findDelimiter($downloadPathFile);

        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $worksheet = $sExcel->readFile($downloadPathFile, true, $delimiter);

        $worksheet = $worksheet->getElement(0);
        /* @var $worksheet Marktjagd_Entity_PhpExcel_Worksheet */
        $lines = $worksheet->getData();

        $cArticle = new Marktjagd_Collection_Api_Article();

        foreach ($lines as $line) {
            if (!preg_match($seasonPattern, $line['merchant-category'])) {
                continue;
            }
            $text = trim($line['description']);
            if ($text == 'Für dies Produkt ist zur Zeit keine Beschreibung vorhanden') {
                $text = null;
            }

            $text = $sCharset->convertTextToUtf8($text);

            $tags = preg_replace('#\s?»\s?#', ',', $line['merchant-category']);
            $title = ( $line['brand'] ? $line['brand'] . ' - ' : '' ) . $line['lable'];
            $title = preg_replace('#[^\*]\*$#', '', $title);

            $image = $line['image-url'];

            $reifenlabel='';
            $additionalImages = array();

            if (trim($line['Kraftstoffeffizienzklasse']) != '') {
                $reifenlabel .= '<br />Kraftstoffeffizienzklasse ' . $line['Kraftstoffeffizienzklasse'] . ' ';
                $additionalImages[] = preg_replace('#\[VAL\]#', $line['Kraftstoffeffizienzklasse'], $rollwiederstandURL);
            }
            if (trim($line['Nasshaftungsklasse']) != '') {
                $reifenlabel .= '<br />Nasshaftungsklasse ' . $line['Nasshaftungsklasse'] . ' ';
                $additionalImages[] = preg_replace('#\[VAL\]#', $line['Nasshaftungsklasse'], $nasslaufEigenschaftenURL);
            }
            if (trim($line['Rollgeraeuschklasse']) != '') {
                $reifenlabel .= '<br />Rollgeraeuschklasse ' . $line['Rollgeraeuschklasse'] . ' ';
                $additionalImages[] = preg_replace('#\[VAL\]#', $line['Rollgeraeuschklasse'], $geraeuschklasseURL);
            }
            if (trim($line['ExternesRollgeraeusch']) != '' && $line['ExternesRollgeraeusch'] > 0) {
                $reifenlabel .= '<br />ExternesRollgeraeusch ' . $line['ExternesRollgeraeusch'] . ' ';
                $additionalImages[] = preg_replace('#\[VAL\]#', $line['ExternesRollgeraeusch'], $geraeuschdbURL);
            }
            if (trim($line['Reifenklassse']) != '') {
                $reifenlabel .= '<br />Reifenklassse ' . $line['Reifenklassse'] . ' ';
            }

            if ($reifenlabel != '') {
                $text .= '<br>'.$reifenlabel;
            }

            if (count($additionalImages)) {
                $image .= ',';
            }

            if (preg_match('#^[0-9]{12,13}$#', $line['product-id'])){
                $ean = $line['product-id'];
            } else {
                $ean = '';
            }

            $text .= '<br><br>Bitte prüfen Sie den Vorrat in der Filiale direkt auf www.reifen.com.'
                    . '<br>Beim Filialkauf können Sie direkt weitere Serviceleistungen vor Ort online buchen.';
                    
            
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setTags($tags)
                     ->setArticleNumber($line['offer-id'])
                     ->setTitle($title)
                     ->setEan($ean)
                     ->setTrademark($line['brand'])
                     ->setText($text)
                     ->setPrice($line['prices'])
                     ->setImage($image . implode(',', $additionalImages))
                     ->setUrl($line['offer-url']);

            $cArticle->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }
}