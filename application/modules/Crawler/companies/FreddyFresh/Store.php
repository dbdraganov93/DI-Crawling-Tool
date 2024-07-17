<?php

/**
 * Store Crawler für Freddy Fresh (ID: 70973)
 */
class Crawler_Company_FreddyFresh_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.freddy-fresh.de/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#class="([^"]*main-store-data.+?)<div\s*class="row\-fluid\s*button\-row"#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            if (preg_match('#<p[^>]*class="store\-contact"[^>]*>(.*?)<br>#', $singleStore, $matchContact)) {
                $aAddress = preg_split('#\s*\,\s*#', $matchContact[1]);
                $eStore->setStreetAndStreetNumber($aAddress[0])
                       ->setZipcodeAndCity($aAddress[1]);
            } else {
                $this->_logger->err($companyId . ': unable to get store address for store ' . $singleStore);
                continue;
            }

            if (preg_match('#<b>eMail\:</b>\s*(.*?)\s*</span>#', $singleStore, $matchMail)) {
                $eStore->setEmail($matchMail[1]);
            }
            
            $pattern = '#href="tel:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            if (preg_match_all('#<span\s*class="store\-opening\-day[^"]*">(.*?)\:\s*</span>\s*'
                . '<span\s*class="store\-opening\-time[^"]*">(.*?)</span>#', $singleStore, $matchOpenings)) {

                $sOpening = '';
                foreach ($matchOpenings[1] as $key => $day) {
                    if (strlen($sOpening) > 0) {
                        $sOpening .= ', ';
                    }

                    $sOpening .= $day . ' ' . $matchOpenings[2][$key];
                }

                $eStore->setStoreHoursNormalized($sOpening);
            }

            $eStore->setWebsite('http://freddyfresh.simplywebshop.de/storedata/listStore');
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}