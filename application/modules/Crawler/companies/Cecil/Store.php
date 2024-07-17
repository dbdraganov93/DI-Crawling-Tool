<?php

/*
 * Store Crawler für CECIL (ID: 71786)
 */

class Crawler_Company_Cecil_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.cecil.de/';
        $searchUrl = $baseUrl . 'shopfinder/Deutschland/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="(' . $searchUrl . '\d\/)"#';
        if (!preg_match_all($pattern, $page, $zipcodeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any zipcode urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($zipcodeUrlMatches[1] as $singleZipcodeUrl) {
            $sPage->open($singleZipcodeUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*id="map_canvas"[^>]*>.*?</div>\s*<p[^>]*>(.+?)</p#s';
            if (!preg_match($pattern, $page, $storeUrlListMatch)) {
                $this->_logger->err($companyId . ': unable to get store url list ' . $singleZipcodeUrl);
                continue;
            }

            $pattern = '#<a[^>]*href="(https:\/\/www\.cecil\.de\/shopfinder\/[A-Z][^"]+?\/)"#';
            if (!preg_match_all($pattern, $storeUrlListMatch[1], $storeUrlMatches)) {
                $this->_logger->err($companyId . ': unable to get any store urls from list ' . $singleZipcodeUrl);
                continue;
            }

            foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                $sPage->open($singleStoreUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<div[^>]*id="map_canvas"[^>]*>\s*</div>\s*<p[^>]*>(.+?)</div>\s*</div#s';
                if (!preg_match($pattern, $page, $storeDetailUrlListMatch)) {
                    $this->_logger->err($companyId . ': unable to get store detail url list ' . $singleStoreUrl);
                    continue;
                }
                
                $pattern = '#<a[^>]*href="(https:\/\/www\.cecil\.de\/shopfinder\/[A-Z][^"]+?\/)"#';
                if (!preg_match_all($pattern, $storeDetailUrlListMatch[1], $storeDetailUrlMatches)) {
                    $this->_logger->err($companyId . ': unable to get any store detail urls from list ' . $singleZipcodeUrl);
                    continue;
                }

                foreach ($storeDetailUrlMatches[1] as $singleStoreDetailUrl) {
                    if (!preg_match('#\/cecil#i', $singleStoreDetailUrl)) {
                        continue;
                    }
                    $sPage->open($singleStoreDetailUrl);
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#<div[^>]*id="map_canvas"[^>]*>\s*</div>\s*<p[^>]*>(.+?)</div>\s*</div#s';
                    if (!preg_match($pattern, $page, $storeInfoMatch)) {
                        $this->_logger->err($companyId . ': unable to get store info list: ' . $singleStoreDetailUrl);
                        continue;
                    }

                    $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4,5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
                    if (!preg_match($pattern, $storeInfoMatch[1], $addressMatch)) {
                        $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreDetailUrl);
                    }
                    
                    $eStore = new Marktjagd_Entity_Api_Store();
                    
                    $pattern = '#Tel\.?:?\s*([^<]+?)\s*<#';
                    if (preg_match($pattern, $storeInfoMatch[1], $phoneMatch)) {
                        $eStore->setPhoneNormalized($phoneMatch[1]);
                    }
                    
                    $pattern = '#ffnungszeiten:(.+)#i';
                    if (preg_match($pattern, $storeInfoMatch[1], $storeHoursMatch)) {
                        $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                    }
                    
                    $eStore->setAddress($addressMatch[1], $addressMatch[2])
                            ->setWebsite($singleStoreDetailUrl);
                    
                    if (strlen($eStore->getZipcode()) == 4) {
                        $eStore->setZipcode(str_pad($eStore->getZipcode(), 5, '0', STR_PAD_LEFT));
                    }
                    
                    $cStores->addElement($eStore);
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
