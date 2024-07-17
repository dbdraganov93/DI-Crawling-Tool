<?php

/*
 * Store Crawler für Lotto Hessen (ID: 71775)
 */

class Crawler_Company_LottoHessen_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.lotto-hessen.de/';
        $searchUrl = $baseUrl . 'controller/RetailerController/showShopsWithinRadius?gbn=5&showcities&'
                . 'lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&'
                . 'lon=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.1);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#var\s*gStoreData\s*=\s*\[\s*([^\]]{10,}?)\s*\];#s';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': no stores for ' . $singleUrl);
                continue;
            }
            
            if (!strlen(trim($storeListMatch[1]))) {
                continue;
            }
            
            $pattern = '#\{\s*([^\}]+?)\s*\}#';
            if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
                $this->_logger->err($companyId . ': no stores from list for ' . $singleUrl);
                continue;
            }
            
            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#([a-zäöü]+)\s*:\s*[\'|\"]?([^\'\",]+)[\'|\"]?#';
                if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                    $this->_logger->err($companyId . ': unable to get any store infos from ' . $singleStore);
                    continue;
                }
                
                $aInfos = array_combine($infoMatches[1], $infoMatches[2]);
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#openingtime:(.+)#';
                if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized(preg_replace('#00:00\s*-\s*00:00#', '-', $storeHoursMatch[1]));
                }
                
                $eStore->setStoreNumber($aInfos['storeid'])
                        ->setLatitude($aInfos['latitude'])
                        ->setLongitude($aInfos['longitude'])
                        ->setStreetAndStreetNumber($aInfos['street'])
                        ->setZipcode($aInfos['zip'])
                        ->setCity($aInfos['city'])
                        ->setPhoneNormalized($aInfos['telephone']);
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
