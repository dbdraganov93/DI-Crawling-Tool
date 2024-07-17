<?php

/* 
 * Store Crawler für Clean Car (ID: 71022)
 */

class Crawler_Company_CleanCar_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.cleancar.de/';
        $searchUrl = $baseUrl . '?tx_brastation_stationoverview[action]=list&tx_brastation_stationoverview[controller]=Station&type=1459414983/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $jStores = preg_replace('#\s+#', ' ', $sPage->getPage()->getResponseAsJson()->root->html);
               
        $pattern = '#<div[^>]*class="station-info"[^>]*>(.+?)</div>\s*</div>\s*</div>\s*</div>#s';
        if (!preg_match_all($pattern, $jStores, $storeMatches)) {
            throw new Exception ($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#Adresse\s*</h4>(.+?)</ul#';
            if (!preg_match($pattern, $singleStore, $addressListMatch)) {
                $this->_logger->err($companyId . ': unable to get store address list: ' . $singleStore);
                continue;
            }
            
            $pattern = '#<li[^>]*>\s*(.+?)\s*</li#';
            if (!preg_match_all($pattern, $addressListMatch[1], $addressInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get store address infos from list: ' . $singleStore);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            for ($i = 0; $i < count($addressInfoMatches[1]); $i++) {
                $pattern = '#^\d{5}#';
                if (preg_match($pattern, $addressInfoMatches[1][$i])) {
                    $eStore->setAddress($addressInfoMatches[1][$i - 1], $addressInfoMatches[1][$i]);
                    continue;
                }
                
                $pattern = '#Tel\.:?(.+)#';
                if (preg_match($pattern, $addressInfoMatches[1][$i], $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                    continue;
                }
                
                $pattern = '#Fax\.:?(.+)#';
                if (preg_match($pattern, $addressInfoMatches[1][$i], $faxMatch)) {
                    $eStore->setFaxNormalized($faxMatch[1]);
                    continue;
                }
                
                $pattern = '#mailto:(([^\@]+?)\@[^"]+?)"#';
                if (preg_match($pattern, $addressInfoMatches[1][$i], $mailMatch)) {
                    $eStore->setEmail($mailMatch[1])
                            ->setStoreNumber($mailMatch[2]);
                    continue;
                }
            }
            
            $pattern = '#ffnungszeiten(.+?)</dl#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}