<?php

/*
 * Store Crawler für Altmärker Fleisch- und Wurstwaren (ID: 71575)
 */

class Crawler_Company_AFWW_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.altmaerker.de/';
        $searchUrl = $baseUrl . 'unternehmen/filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/(unternehmen\/filialen\/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlListMatches)) {
            throw new Exception($companyId . ': unable to get any store list urls: ' . $searchUrl);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlListMatches[1] as $singleStoreListUrl) {
            $storeListUrl = $baseUrl . $singleStoreListUrl;

            $sPage->open($storeListUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<h3[^>]*class="catItemTitle"[^>]*>\s*<a[^>]*href="\/(unternehmen\/filialen\/[^"]+?)"#';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                throw new Exception($companyId . ': unable to get any store urls: ' . $storeListUrl);
            }
            
            foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                $storeDetailUrl = $baseUrl . $singleStoreUrl;
                
                $sPage->open($storeDetailUrl);
                $page = $sPage->getPage()->getResponseBody();
                
                $pattern = '#<div[^>]*class="itemFullText"[^>]*>(.+?)</div#';
                if (!preg_match($pattern, $page, $infoListMatch)) {
                    $this->_logger->err($companyId . ': unable to get store info list: ' . $storeDetailUrl);
                    continue;
                }
                
                $pattern = '#>([^<]+?)(\s*<[^>]*>\s*)*(\s*\d{5}\s+[^<]+?)<#';
                if (!preg_match($pattern, $infoListMatch[1], $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#ffnungszeiten(.+)#';
                if (preg_match($pattern, $infoListMatch[1], $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }
                
                $pattern = '#Tel([^<]+?)<#';
                if (preg_match($pattern, $infoListMatch[1], $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }
                
                $eStore->setAddress($addressMatch[1], $addressMatch[3]);
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
