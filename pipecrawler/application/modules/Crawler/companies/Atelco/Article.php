<?php
/**
 * Artikelcrawler für Atelco (ID: 28670)
 */
class Crawler_Company_Atelco_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        ini_set('memory_limit', '1G');
        $sDownload = new Marktjagd_Service_Transfer_Download();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadPath = $sHttp->generateLocalDownloadFolder($companyId);
        $downloadPathFile = $sDownload->downloadByUrl(
            'http://www.atelco.de/ai/export/marktjagd.csv',
            $downloadPath);
                
        $header = array();
        $aData = array();
        
        $fh = fopen($downloadPathFile, 'r');
        while (($data = fgetcsv($fh, 0, ';')) != FALSE) {
            if (!count($header)) {
                $header = $data;
                continue;
            }
            $aData[] = array_combine($header, $data);
        }
        fclose($fh);
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleArticle) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            foreach ($singleArticle  as $articleKey => $articleValue) {
                $strKey = ucwords($articleKey);
                if (preg_match('#\_([a-z]{1})#', $articleKey)) {
                    $aKey = preg_split('#\_#', $articleKey);
                    for ($i = 0; $i < count($aKey); $i++) {
                        $aKey[$i] = ucwords($aKey[$i]);
                    }
                    $strKey = implode('', $aKey);
                }
                $eArticle->{'set' . $strKey}($articleValue);
            }
                        
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}