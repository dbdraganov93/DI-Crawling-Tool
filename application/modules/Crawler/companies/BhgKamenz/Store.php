<?php

/*
 * Store Crawler für BHG Kamenz (ID: 71789)
 */

class Crawler_Company_BhgKamenz_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.bhg-kamenz.de//';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class=\\\"cnt-item[^>]*vm-adresse\\\"[^>]*>(.+?),\s*\'click\'#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#div[^>]*class=\\\"anschrift\\\"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $streetMatch)) {
                $this->_logger->err($companyId . ': unable to get store street ' . $singleStore);
                continue;
            }
            
            $pattern = '#>\s*(\d{5})(\s*<[^>]*>\s*)*([^<]+?)<#';
            if (!preg_match($pattern, $singleStore, $cityMatch)) {
                $this->_logger->err($companyId . ': unable to get store city ' . $singleStore);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#div[^>]*class=\\\"telefon\\\"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#div[^>]*class=\\\"fax\\\"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#href=\\\"mailto:([^\\\]+?)\\\#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $eStore->setStreetAndStreetNumber(preg_replace('#\s*und\s*.+#', '', $streetMatch[1]))
                    ->setZipcode($cityMatch[1])
                    ->setCity($cityMatch[3]);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
