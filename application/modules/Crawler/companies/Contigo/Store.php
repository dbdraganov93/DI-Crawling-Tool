<?php

/**
 * Store Crawler für Contigo (ID: 71364)
 */
class Crawler_Company_Contigo_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://contigo.de/';
        $searchUrl = $baseUrl . 'contigo-fairtrade-shops/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#node\.onclick\s*=\s*function\(\)\s*\{\s*self\.location\.href\s*=\s*"(http:\/\/contigo\.de\/contigo-fairtrade-shops[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (array_unique($storeUrlMatches[1]) as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#div[^>]*class="adress"[^>]*>(.+?)</div>\s*</div#';
            if (!preg_match($pattern, $page, $infoMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $singleStoreUrl);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $infoMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten(.+)#';
            if (preg_match($pattern, $infoMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#fon:?\s*<[^>]*>\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $infoMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#fax:?\s*<[^>]*>\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $infoMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $infoMatch[1], $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($singleStoreUrl);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
