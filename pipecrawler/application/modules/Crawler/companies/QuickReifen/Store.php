<?php

/**
 * Storecrawler für Quick Reifendiscount (ID: 67390)
 */
class Crawler_Company_QuickReifen_Store extends Crawler_Generic_Company {

    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page();
        $sGeneratorUrl = new Marktjagd_Service_Generator_Url();
        $cStores = new Marktjagd_Collection_Api_Store();

        $baseUrl = 'https://www.quick.de/';
        $searchUrl = $baseUrl . 'de/store-finder'
                . '?q=' . $sGeneratorUrl::$_PLACEHOLDER_ZIP
                . '&searchBySHOPOTPPAYMENT=true';

        $this->_logger->log('Quick Reifendiscount (ID: 67390): generating search urls', Zend_Log::INFO);
        $aUrl = $sGeneratorUrl->generateUrl($searchUrl, $sGeneratorUrl::$_TYPE_ZIP, '50');

        foreach ($aUrl as $url) {
            $this->_logger->log('Quick Reifendiscount (ID: 67390): open search url ' . $url, Zend_Log::INFO);
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#jsconfig\.storefinder\.results\.push\((.+?)\);#is';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': not stores for ' . $url);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#<[^>]*class="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
                if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                    $this->_logger->err($companyId . ': unable to get any store infos: ' . $singleStore);
                    continue;
                }

                $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStreetAndStreetNumber($aInfos['street'])
                        ->setZipcodeAndCity($aInfos['city']);

                $pattern = '#href="\/(store[^"]+?)"#';
                if (preg_match($pattern, $singleStore, $urlMatch)) {
                    $storeDetailUrl = $baseUrl . $urlMatch[1];

                    $sPage->open($storeDetailUrl);
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
                    if (!preg_match_all($pattern, $page, $infoMatches)) {
                        $this->_logger->err($companyId . ': unable to get any store infos from detail site: ' . $storeDetailUrl);
                        continue;
                    }
                    
                    $aDetailInfo = array_combine($infoMatches[1], $infoMatches[2]);
                    
                    $eStore->setPhoneNormalized($aDetailInfo['telephone'])
                            ->setEmail($aDetailInfo['email'])
                            ->setWebsite($storeDetailUrl);
                    
                    $pattern = '#table[^>]*class="openingHours"[^>]*>(.+?)</table#s';
                    if (preg_match($pattern, $page, $storeHoursMatch)) {
                        $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                    }
                }

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
