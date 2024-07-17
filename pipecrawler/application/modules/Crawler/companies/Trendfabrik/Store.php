<?php

/*
 * Store Crawler für Trendfabrik (ID: 71835)
 */

class Crawler_Company_Trendfabrik_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://info.trendfabrik.de/';
        $searchUrl = $baseUrl . 'filialen';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*class="promo-block"[^>]*href="(https:\/\/info\.trendfabrik\.de\/branch\/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)(\s*<[^>]*>\s*)+Telefon:?\s*([^<]+?)\s*<#s';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten(.+?)</div#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#fax:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
                                    
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setPhoneNormalized(preg_replace(array('#\+49#', '#\(#', '#\)#', '#\s+#', '#–#'), '', $addressMatch[4]))
                    ->setWebsite($singleStoreUrl);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
