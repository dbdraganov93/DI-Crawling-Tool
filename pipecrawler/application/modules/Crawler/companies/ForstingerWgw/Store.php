<?php

/*
 * Store Crawler für Forstinger WGW (ID: 72290)
 */

class Crawler_Company_ForstingerWgw_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.forstinger.com/';
        $searchUrl = $baseUrl . 'fachwerkstatt/filialfinder/';

        $ch = curl_init($searchUrl);
        
        $curlFile = new CURLFile('fileName', 'form-data', '1010');
        $data = array('tx_rtgforstinger_pi1[address]' => $curlFile);
        
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
                array(
                    'search' => 'true',
                    $data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);

        $pattern = '#<div[^>]*id="table-item_\d+"[^>]*>\s*(.+?)\s*<\/div>\s*<\/div#s';
        if (!preg_match_all($pattern, $result, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $singleStore = preg_replace('#\s{2,}#', ' ', $singleStore);
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten(.+?)</div#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#Tel\.?:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#<img[^>]*title="([^"]+?)"#';
            if (preg_match_all($pattern, $singleStore, $serviceMatches)) {
                $eStore->setService(implode(', ', $serviceMatches[1]));
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
