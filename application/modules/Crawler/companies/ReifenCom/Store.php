<?php

/**
 * Store Crawler für Reifen.com (ID: 28940)
 */
class Crawler_Company_ReifenCom_Store extends Crawler_Generic_Company {
        
    public function crawl($companyId) {
        $csvPath = 'https://media.reifen.com/fileadmin/files/RC-Artikellisten/Filialliste_marktjagd.csv';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        
        $sMjCsv = new Marktjagd_Service_Input_MarktjagdCsv();
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        
        $cMjCsv = new Marktjagd_Collection_Api_Store();
        if ($sHttp->getRemoteFile($csvPath, $localPath) )  {
            $cMjCsv = $sMjCsv->convertToCollection($localPath . 'Filialliste_marktjagd.csv', 'stores');
        }

        $fileName = $sCsv->generateCsvByCollection($cMjCsv);
        return $this->_response->generateResponseByFileName($fileName);
    }
}