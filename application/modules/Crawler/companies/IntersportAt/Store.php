<?php

/*
 * Store Crawler für Intersport AT (ID: 72293)
 */

class Crawler_Company_IntersportAt_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.intersport.at/';
        $searchUrl = $baseUrl . 'haendlersuche/haendlerliste';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li><a[^>]*class="js-productgrid-ajax-link"[^>]*href="\?page=(\d+)">\d+</a>\s*</li>\s*<li>\s*<a[^>]*>\s*<span[^>]*class="icon icon-arrow-right"#';
        if (!preg_match($pattern, $page, $lastSiteMatch)) {
            throw new Exception($companyId . ': unable to get last site number.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 1; $i <= $lastSiteMatch[1]; $i++) {
            $sPage->open($searchUrl . '?page=' . $i);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*store-name[^>]*>\s*<a href="\/([^"]+?)"#';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                throw new Exception($companyId . ': unable to get any store urls.');
            }

            foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                $storeDetailUrl = $baseUrl . $singleStoreUrl;
                
                $sPage->open($storeDetailUrl);
                $page = $sPage->getPage()->getResponseBody();
                
                $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
                if (!preg_match_all($pattern, $page, $infoMatches)) {
                    $this->_logger->err($companyId . ': unable to get any store infos: ' . $storeDetailUrl);
                    continue;
                }
                
                $aAddress = array_combine($infoMatches[1], $infoMatches[2]);
                
                $aCity = preg_split('#\s*,\s*#', $aAddress['addressLocality']);
                $aStreet = preg_split('#\s*,\s*#', $aAddress['streetAddress']);
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#href="tel:([^"]+?)"#';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }
                
                $pattern = '#ffnungszeiten(.+?)</dl#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }
                
                $eStore->setStreetAndStreetNumber(end($aStreet))
                        ->setZipcode(preg_replace('#[^\d]#', '', $aAddress['postalCode']))
                        ->setCity($aCity[0])
                        ->setWebsite($storeDetailUrl);
                
                if (preg_match('#straße#i', $aStreet[0])) {
                    $eStore->setStreetAndStreetNumber($aStreet[0]);
                }
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
