<?php

/**
 * Store Crawler für Rüegg Kamine (ID: 71344)
 */
class Crawler_Company_Rueegg_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.ruegg-cheminee.com/';
        $searchUrl = $baseUrl . 'modules/mod_partner/plugins/standard/markers.cfm';
        $detailUrl = $baseUrl . 'modules/mod_partner/plugins/standard/marker_detail.cfm?id=';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->markers as $singleJStore) {
            $sPage->open($detailUrl . $singleJStore->id);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#href="tel:(\+49[^"]+?)"#';
            if (!preg_match($pattern, $page, $phoneMatch)) {
                $this->_logger->info($companyId . ': not an german store: ' . $singleJStore->id);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#class="address"[^>]*>([^<]+?)<[^>]*>\s*(\d{5}\s+[^<]+?)<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->info($companyId . ': unable to get store address: ' . $singleJStore->id);
                continue;
            }
            
            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#href="([^"]+?)"[^>]*taget="_blank"#';
            if (preg_match($pattern, $page, $websiteMatch)) {
                $eStore->setWebsite($websiteMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setPhoneNormalized($phoneMatch[1])
                    ->setStoreNumber($singleJStore->id);
            
            $cStores->addElement($eStore, TRUE);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
