<?php

/**
 * Store Crawler für Groschen Markt (ID: 69973)
 */
class Crawler_Company_GroschenMarkt_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.groschen-markt.eu/';
        $storeListUrl = $baseUrl . 'groschen-markt%20filialen-abisz.htm';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($storeListUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<span[^>]*>\s*<a[^>]*href="(groschen%20markt%20.+?)"[^>]*>#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $storeUrl) {
            $storeDetailUrl = $baseUrl . $storeUrl;
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<span[^>]*class="xr_tl[^>]*>([^\|<]+?\|[^<]+?)<#';
            if (!preg_match_all($pattern, $page, $contactMatches)) {
                $this->_logger->err($companyId . ': unable to get store contact infos: ' . $storeDetailUrl);
                continue;
            }
            
            $aAddress = preg_split('#\s*\|\s*#', $contactMatches[1][0]);
            $aContact = preg_split('#\s*\|\s*#', $contactMatches[1][1]);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten:?\s*</span>(.+?)</div#';
            if(preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setAddress($aAddress[count($aAddress) - 2], $aAddress[count($aAddress) - 1])
                    ->setPhoneNormalized($aContact[0])
                    ->setFaxNormalized($aContact[1])
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore, TRUE);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}