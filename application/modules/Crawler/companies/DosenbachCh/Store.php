<?php

/*
 * Store Crawler für Dosenbach CH (ID: 72182)
 */

class Crawler_Company_DosenbachCh_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.dosenbach.ch/';
        $searchUrl = $baseUrl . 'CH/de/shop/resultStore.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aParams = array(
            'inline_commandtype' => 'ZGUuZ2V0aXQuaHlicmlzLmRlaWNobWFubi53ZWIuc3RvcmVzZWFyY2guYmluZGluZ3MuU3RvcmVTZWFyY2hDb21tYW5k',
            'inline_formid' => 'U3RhcnRTdG9yZVNlYXJjaEZvcm0=',
            'country' => 'CH',
            'javaScriptGeocoded' => 'true'
        );

        $aZipcodes = $sDbGeo->findAll('CH');
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $aParams['address'] = $singleZipcode->getZipcode();
            $aParams['result'] = $singleZipcode->getZipcode() . ' ' . $singleZipcode->getCity() . '|' . $singleZipcode->getLatitude() . ',' . $singleZipcode->getLongitude();
            
            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#var\s*locations\s*=\s*(\[[^\]]+?\]);#';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': no store list for zipcode: ' . $singleZipcode->getZipcode());
                continue;
            }
            
            $jStores = json_decode($storeListMatch[1]);
            foreach ($jStores as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setLatitude($singleJStore->latitude)
                        ->setLongitude($singleJStore->longitude)
                        ->setStreetAndStreetNumber($singleJStore->street, 'CH')
                        ->setSubtitle($singleJStore->remark)
                        ->setZipcode($singleJStore->zip)
                        ->setCity($singleJStore->city)
                        ->setPhoneNormalized($singleJStore->phone)
                        ->setStoreNumber($singleJStore->name)
                        ->setStoreHoursNormalized($singleJStore->openingDays, 'text', FALSE, 'fra');
                
                if (!strlen($eStore->getStoreHours())) {
                    $eStore->setStoreHoursNormalized($singleJStore->openingDays, 'text', FALSE, 'ita');
                }
                                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
