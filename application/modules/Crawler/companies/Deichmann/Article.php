<?php
/**
 * Artikelcrawler für Deichmann (ID: 341)
 */
class Crawler_Company_Deichmann_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $url = 'http://feeds.metalyzer.com/deichmann/de/feed_deichmannDE_marktjagd.csv';
        $sDownload = new Marktjagd_Service_Transfer_Download();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadPath = $sHttp->generateLocalDownloadFolder($companyId);
        $downloadPathFile = $sDownload->downloadByUrl(
            $url,
            $downloadPath);

        $sMjCsv = new Marktjagd_Service_Input_MarktjagdCsv();

        /* @var $cArticle Marktjagd_Collection_Api_Article */
        $cArticle = $sMjCsv->convertToCollection($downloadPathFile, 'articles');        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }
}