<?php

/**
 * Storecrawler für Atelco (ID: 28670)
 */
class Crawler_Company_Atelco_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $url = 'http://www.atelco.de/ai/export/marktjagd_stores.csv';

        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadFolder = $sHttp->generateLocalDownloadFolder($companyId);
        $sDownload = new Marktjagd_Service_Transfer_Download();
        $fileName = $sDownload->downloadByUrl($url, $downloadFolder);

        $mjCsv = new Marktjagd_Service_Input_MarktjagdCsv();
        /* @var $cStore Marktjagd_Collection_Api_Store */
        $cStore = $mjCsv->convertToCollection($fileName, 'stores');
        
        $cStore->removeElement(14);
        $cStore->removeElement(23);
        $cStore->removeElement(22);
        $cStore->removeElement(19);
        $cStore->removeElement(20);
        $cStore->removeElement(27);
        $cStore->removeElement(9);
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}