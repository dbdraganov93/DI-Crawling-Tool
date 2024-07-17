<?php

/*
 * Store Crawler für Naturgut (ID: 385)
 */

class Crawler_Company_Naturgut_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.biomarkt.de/';
        $searchUrl = $baseUrl . '5586_MaerktenachPLZ_Bereichen.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $aParams = array(
            'character_search' => 'A',
            'pid' => '5586'
        );

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aNaturGutUrls = array();

        for ($i = 0; $i < 10; $i++) {
            $aParams['plz_search'] = $i;

            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*class="s_link[^>]*site_b\.value=\'(\d+?)\'[^>]*site_a\.value=\'(\d+?)\'#';
            if (!preg_match_all($pattern, $page, $siteMatches)) {
                $this->_logger->err($companyId . ': unable to get any site values.');
                continue;
            }

            for ($j = 1; $j <= end($siteMatches[2]); $j++) {
                $aParams = array_merge(
                        $aParams, array(
                    'site_b' => $siteMatches[1][$j],
                    'site_a' => $siteMatches[2][$j]
                        )
                );

                $sPage->open($searchUrl, $aParams);
                $page = $sPage->getPage()->getResponseBody();
                
                $pattern = '#<a[^>]*href="(\d{4}_NATURGUT_[^"]+?)"[^>]*class="s_link"[^>]*>\s*<b#';
                if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                    $this->_logger->info($companyId . ': unable to get any NATURGUT store urls: ' . $i);
                    continue;
                }
                
                $aNaturGutUrls = array_merge($aNaturGutUrls, $storeUrlMatches[1]);
            }
        }
        
        $aNaturGutUrls = array_unique($aNaturGutUrls);
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('GET');
        $sPage->setPage($oPage);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aNaturGutUrls as $singleUrl) {
            $storeDetailUrl = $baseUrl . $singleUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten(.+?)</span>\s*<br[^>]*>\s*<br[^>]*>\s*<br[^>]*>#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#Telefon:\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#Telefax:\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
