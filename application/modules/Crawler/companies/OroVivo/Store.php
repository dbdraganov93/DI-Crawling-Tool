<?php

/**
 * Store Crawler für OroVivo (ID: 29130)
 */
class Crawler_Company_OroVivo_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.orovivo.de/';
        $searchUrl = $baseUrl . 'de/unsere-filialen/unsere-filialen?storeParam%5Bcode%5D=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 10);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*class="link"[^>]*href="([^"]+?)">Diese\s*Filiale\s*ansehen#s';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                $this->_logger->info($companyId . ': unable to get any stores for ' . $singleUrl);
                continue;
            }
            
            foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                $sPage->open($singleStoreUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<div[^>]*class="address-wrapper">\s*(.+?)\s*</div#s';
                if (!preg_match($pattern, $page, $addressListMatch)) {
                    $this->_logger->err($companyId . ': unable to get any store address list: ' . $singleStoreUrl);
                    continue;
                }
                
                $pattern = '#itemprop="(postalCode|addressLocality|address)"[^>]*>\s*([^<]+?)\s*<#';
                if (!preg_match_all($pattern, $addressListMatch[1], $addressMatches)) {
                    $this->_logger->err($companyId . ': unable to get any store address from list: ' . $singleStoreUrl);
                    continue;
                }
                
                $aAddress = array_combine($addressMatches[1], $addressMatches[2]);

                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#<time[^>]*itemprop="openingHours"[^>]*datetime="([^"]+?)"#';
                if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                    $eStore->setStoreHoursNormalized(implode(',', $storeHoursMatches[1]));
                }
                
                $pattern = '#itemprop="phone"[^>]*>([^<]+?)<#';
                if (preg_match($pattern, $addressListMatch[1], $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }
                
                $pattern = '#<div[^>]*class="services"[^>]*>(.+?)</ul>#s';
                if (preg_match($pattern, $page, $serviceListMatch)) {
                    $pattern = '#<li[^>]*>\s*([^<]+?)\s*<#';
                    if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                        $eStore->setService(implode(', ', $serviceMatches[1]));
                    }
                }
                
                $eStore->setStreetAndStreetNumber($aAddress['address'])
                        ->setZipcode($aAddress['postalCode'])
                        ->setCity(ucwords(strtolower($aAddress['addressLocality'])));

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
