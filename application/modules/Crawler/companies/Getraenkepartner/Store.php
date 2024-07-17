<?php

/* 
 * Store Crawler für Getränkepartner (ID: 71877)
 */

class Crawler_Company_Getraenkepartner_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.getraenke-partner.de/';
        $searchUrl = $baseUrl . 'index.php';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();
        
        $aZipcodes = $sDbGeo->findZipCodesByNetSize(10);
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);
        
        $aParams = array(
            'id' => '97',
            'Submit' => 'PLZ-Suche'
        );
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $aParams['plz'] = $singleZipcode;
            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<p[^>]*class="partner_content"[^>]*>\s*(.+?)\s*</p#';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': no stores for zipcode: ' . $singleZipcode);
                continue;
            }
            
            foreach ($storeMatches[1] as $singleStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $aInfos = preg_split('#\s*<br[^>]*>\s*#', $singleStore);
                
                for ($i = 0; $i < count($aInfos); $i++) {
                    if (preg_match('#\d{5}\s+[A-Z]#', $aInfos[$i])) {
                        $eStore->setAddress($aInfos[$i - 1], $aInfos[$i]);
                        continue;
                    }
                    if (preg_match('#fon#i', $aInfos[$i])) {
                        $eStore->setPhoneNormalized($aInfos[$i]);
                        continue;
                    }
                }
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}