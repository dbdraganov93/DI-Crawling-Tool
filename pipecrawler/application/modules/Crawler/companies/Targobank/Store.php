<?php

/**
 * Store Crawler für Targo Bank (ID: 71659)
 */
class Crawler_Company_Targobank_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.targobank.de/';
        $searchUrl = $baseUrl . 'de/service/suchen-und-finden/SearchList.aspx?loca='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&Btn.Find.x=0&Btn.Find.y=0&type=branch';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 50);

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#href="(details\.aspx[^"]+?)"#i';
            if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores for: ' . $singleUrl);
                continue;
            }

            foreach ($storeLinkMatches[1] as $singleStoreLink) {
                $storeUrl = $baseUrl . 'de/service/suchen-und-finden/' . $singleStoreLink;
                
                $sPage->open($storeUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#si';
                if (!preg_match_all($pattern, $page, $storeInfoMatches)) {
                    $this->_logger->info($companyId . ': unable to get any store infos for: ' . $storeUrl);
                }

                $aInfo = array_combine($storeInfoMatches[1], $storeInfoMatches[2]);
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#class="days[^>]*>(.+?)</table#is';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }

                $pattern = '#class="titre3"[^>]*>\s*services(.+?)</ul#si';
                if (preg_match($pattern, $page, $serviceListMatch)) {
                    $pattern = '#<li[^>]*>\s*(.+?)\s*</li#si';
                    if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                        $eStore->setService(implode(', ', $serviceMatches[1]));
                    }
                }

                $pattern = '#targetlat=(.+?)\'.+?targetlng=(.+?)\'#s';
                if (preg_match($pattern, $page, $geoMatch)) {
                    $eStore->setLatitude($geoMatch[1])
                            ->setLongitude($geoMatch[2]);
                }

                $eStore->setStoreNumber($aInfo['branchCode'])
                        ->setStreetAndStreetNumber($aInfo['streetAddress'])
                        ->setZipcode($aInfo['postalCode'])
                        ->setCity(ucwords(strtolower($aInfo['addressLocality'])))
                        ->setPhoneNormalized($aInfo['telephone'])
                        ->setFaxNormalized($aInfo['faxNumber']);

                $cStores->addElement($eStore, true);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
