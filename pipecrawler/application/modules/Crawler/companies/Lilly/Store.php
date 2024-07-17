<?php


/**
 * Store Crawler für Lilly Brautmoden (ID: 22303)
 */
class Crawler_Company_Lilly_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.lilly.de/';
        $searchUrl = $baseUrl . 'shop-finden/brautmode/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*data-dealer-id="(.+?)"[^>]*>(.*?)</div>#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStore = new Marktjagd_Collection_Api_Store();

        foreach ($storeMatches[2] as $keyStore => $storeInfos) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($storeMatches[1][$keyStore]);
            if (preg_match('#<a[^>]*class="js-e-map-list-dealer-name"[^>]*>\s*(.*?)\s*</a>#',$storeInfos, $matchStoreName)) {
                $eStore->setTitle($matchStoreName[1]);
            }

            if (preg_match('#<span[^>]*itemprop="streetAddress"[^>]*>\s*(.*?)\s*</span>#',$storeInfos, $matchStreet)) {
                $eStore->setStreetAndStreetNumber($matchStreet[1]);
            }

            if (preg_match('#<span[^>]*itemprop="addressRegion"[^>]*>\s*(.*?)\s*</span>#',$storeInfos, $matchCity)) {
                $eStore->setCity($matchCity[1]);
            }

            if (preg_match('#<span[^>]*itemprop="postalCode"[^>]*>\s*DE-(.*?)\s*</span>#',$storeInfos, $matchZip)) {
                $eStore->setZipcode($matchZip[1]);
            } else {
                $this->_logger->log('skipped store, not in germany: ' . $eStore->getCity(), Zend_Log::NOTICE);
                continue;
            }

            if (preg_match('#<span[^>]*itemprop="telephone"[^>]*>\s*(.*?)\s*</span>#',$storeInfos, $matchPhone)) {
                $eStore->setPhoneNormalized($matchPhone[1]);
            }

            if (preg_match('#<a[^>]*itemprop="email"[^>]*>\s*(.*?)\s*</a>#',$storeInfos, $matchMail)) {
                $eStore->setEmail($matchMail[1]);
            }

            if (preg_match('#<a[^>]*itemprop="url"[^>]*href="(.*?)"[^>]*>#',$storeInfos, $matchUrl)) {
                $eStore->setWebsite($matchUrl[1]);
            }

            if (preg_match('#<meta[^>]*itemprop="latitude"[^>]*content="(.*?)"[^>]*>#',$storeInfos, $matchLat)) {
                $eStore->setLatitude($matchLat[1]);
            }

            if (preg_match('#<meta[^>]*itemprop="longitude"[^>]*content="(.*?)"[^>]*>#',$storeInfos, $matchLon)) {
                $eStore->setLongitude($matchLon[1]);
            }

            $cStore->addElement($eStore);
            
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}