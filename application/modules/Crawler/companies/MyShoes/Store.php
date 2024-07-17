<?php

/**
 * Store Crawler für MyShoes (ID: 69652)
 */
class Crawler_Company_MyShoes_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.myshoes.de/';
        $searchUrl = $baseUrl . 'DE/de/shop/resultStore.html?latitude='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&longitude='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        
        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        
        foreach ($sFtp->listFiles('.', '#\.xls#') as $singleStoreFile) {
            $localStoreFilePath = $sFtp->downloadFtpToDir($singleStoreFile, $localPath);
            break;
        }
        
        $aData = $sExcel->readFile($localStoreFilePath, TRUE)->getElement(0)->getData();
        
        $pattern = '#(\d{3})#';
        $aStoreNumbersCampaign = array();
        foreach ($aData as $singleStore) {
            if (preg_match($pattern, $singleStore['Vkst'], $storeNumberMatch)
                    && preg_match('#Bayern#', $singleStore['Bundesland'])) {
                $aStoreNumbersCampaign[] = 'MYS' . $storeNumberMatch[1];
            }
        }
        
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.2);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
                    
            $pattern = '#var\s*locations\s*=\s*\[([^;]+?)\];#';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': no store list for: ' . $singleUrl);
                continue;
            }
            
            $pattern = '#(\{[^\}]+?\})#';
            if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
                $this->_logger->err($companyId . ': no stores for: ' . $singleUrl);
                continue;
            }
            
            foreach ($storeMatches[1] as $singleStore) {
                $jStore = json_decode($singleStore);
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setLatitude($jStore->latitude)
                        ->setLongitude($jStore->longitude)
                        ->setStreetAndStreetNumber($jStore->street)
                        ->setZipcode($jStore->zip)
                        ->setCity($jStore->city)
                        ->setPhoneNormalized($jStore->phone)
                        ->setStoreNumber($jStore->name)
                        ->setStoreHoursNormalized($jStore->openingDays);
                
                if (in_array($eStore->getStoreNumber(), $aStoreNumbersCampaign)) {
                    $eStore->setDistribution('August-Kampagne');
                }
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
