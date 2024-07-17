<?php

/*
 * Store Crawler für Tchibo CH (ID: 72171)
 */

class Crawler_Company_TchiboCh_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.tchibo.ch/';
        $searchUrl = $baseUrl . 'tchibo-schweiz-si1.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\.\/(tchibo[^\?]+?)\?.*?"[^>]*class="c\-tp\-textbutton"#';
        if (!preg_match_all($pattern, $page, $storeCityUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls from list.');
        }

        $aStoreDetailUrls = array();
        foreach ($storeCityUrlMatches[1] as $singleStoreCityUrl) {
            $storeCityUrl = $baseUrl . $singleStoreCityUrl;

            $sPage->open($storeCityUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="\.\/(tchibo-[^\?]+?)\?[^>]*>\s*<span[^>]*>\s*Mehr#';
            if (!preg_match_all($pattern, $page, $storeCityUrlMatches)) {
                $this->_logger->info($companyId . ': unable to get any store urls from city url: ' . $storeCityUrl);
                continue;
            }
            $aStoreDetailUrls = array_merge($aStoreDetailUrls, $storeCityUrlMatches[1]);
        }
                
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreDetailUrls as $singleStoreDetailUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreDetailUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if(!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $storeDetailUrl);
                continue;
            }
            
            $aInfo = array_combine($infoMatches[1], $infoMatches[2]);
            
            $pattern = '#-([^\.-]+?)\.html#';
            if (!preg_match($pattern, $singleStoreDetailUrl, $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to to get store number: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<meta[^>]*content="([^"]+?)"[^>]*itemprop="openingHours"#s';
            if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                $eStore->setStoreHoursNormalized(implode(',', $storeHoursMatches[1]));
            }
            
            $pattern = '#<span[^>]*itemprop="telephone"[^>]*>([^<]+?)<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $eStore->setStoreNumber($storeNumberMatch[1])
                    ->setStreetAndStreetNumber($aInfo['streetAddress'], 'CH')
                    ->setZipcode($aInfo['postalCode'])
                    ->setCity($aInfo['addressLocality'])
                    ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore, TRUE);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
