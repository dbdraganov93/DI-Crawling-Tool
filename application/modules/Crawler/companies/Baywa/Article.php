<?php

/**
 * Artikelcrawler für Baywa (ID: 69602) & Hellweg (ID: 28323)
 */
class Crawler_Company_Baywa_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sDownload = new Marktjagd_Service_Transfer_Download();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadPath = $sHttp->generateLocalDownloadFolder($companyId);

        if ($companyId == 69602) {
            // Baywa
            $remotePath = 'https://cloud.hellweg.de/s/kBZtjnCFgTq4tZ3/download';
        } elseif ($companyId == 28323) {
            // Hellweg
            $remotePath = 'https://cloud.hellweg.de/s/GJWxCx2MBkresog/download';
        } elseif ($companyId == 72463) {
            // HellwegAT
            $remotePath = 'https://cloud.hellweg.de/s/99ZjcZ8y7sjDC6q/download';
        } else {
            $this->_logger->err($companyId . ': no valid remote url for company detected');
        }

        $downloadPathFile = $sDownload->downloadByUrl(
                $remotePath, $downloadPath);


        $sCsv = new Marktjagd_Service_Input_Csv();
        $delimiter = $sCsv->findDelimiter($downloadPathFile);

        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $worksheet = $sExcel->readFile($downloadPathFile, true, $delimiter);

        $worksheet = $worksheet->getElement(0);
        /* @var $worksheet Marktjagd_Entity_PhpExcel_Worksheet */
        $lines = $worksheet->getData();

        $cArticle = new Marktjagd_Collection_Api_Article();
        foreach ($lines as $line) {
            $line = preg_replace('#\$#','',$line);
            if ($line['$price$'] == 0) {
                continue;
            }

            #redmine #22902
            if (strlen($line['Artikelnummer']) > 32) {
                $line['Artikelnummer'] = substr($line['Artikelnummer'], 0, 32);
            }

            $aUrl = parse_url($line['$url$']);
            $url = $aUrl['scheme'] . '://' . $aUrl['host'] . $aUrl['path'] . '?' . str_replace('?', '&', $aUrl['query']);

            $eArticle = new Marktjagd_Entity_Api_Article();


            $eArticle->setArticleNumber($line['$article_number$'])
                    ->setTitle($line['$title$'])
                    ->setText($line['$text$'])
                    ->setPrice($line['$price$'])
                    ->setImage($line['$image$'])
                    ->setEan($line['$ean$'])
                    ->setUrl($url);

            $strUrl = preg_replace('#(\.de)([A-Z])#', '$1/$2', $line['Link']);
            $ch = curl_init($strUrl);
            curl_setopt($ch, CURLOPT_HEADER, TRUE);
            curl_setopt($ch, CURLOPT_NOBODY, TRUE);
            $curl = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            if (preg_match('#200#', $info['http_code'])) {
                $eArticle->setUrl($strUrl);
            }

            $cArticle->addElement($eArticle, TRUE, 'complex', FALSE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }

}
