<?php
/**
 * Store Crawler für Cache Cache FR (ID: )
 */

class Crawler_Company_CacheCacheFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.cache-cache.fr/';
        $searchUrl = $baseUrl . 'fr/magasins/recherche.cfm';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeoRegion = new Marktjagd_Database_Service_GeoRegion();

        $aZipcodes = $sDbGeoRegion->findZipCodesByNetSize(25);

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aParams = array(
            'latGeoLoc' => '',
            'longGeoLoc' => ''
        );

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $aParams['searchTxt'] = $singleZipcode;
            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="infosMag"[^>]*>\s*<a[^>]*href="([^"]+?)"[^>]*title="Adresse#';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                $this->_logger->info($companyId . ': no stores for zipcode: ' . $singleZipcode);
                continue;
            }

            foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                $sPage->open($singleStoreUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<section[^>]*id="magDetail"[^>]*>(.+?)<\/script>#';
                if (!preg_match($pattern, $page, $storeInfoListMatch)) {
                    $this->_logger->err($companyId . ': unable to get store info list: ' . $singleStoreUrl);
                    continue;
                }

                $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
                if (!preg_match_all($pattern, $storeInfoListMatch[1], $addressMatches)) {
                    $this->_logger->err($companyId . ': unable to get store address info from list: ' . $singleStoreUrl);
                    continue;
                }

                $aAddress = array_combine($addressMatches[1], $addressMatches[2]);

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#-(\d+)\.htm#';
                if (preg_match($pattern, $singleStoreUrl, $storeNumberMatch)) {
                    $eStore->setStoreNumber($storeNumberMatch[1]);
                }

                $pattern = '#<li[^>]*itemprop="openingHours"[^>]*datetime="([^"]+?)"[^>]*>#';
                if (preg_match_all($pattern, $storeInfoListMatch[1], $storeHoursMatches)) {
                    $eStore->setStoreHoursNormalized(implode(',', $storeHoursMatches[1]));
                }

                $pattern = '#<a[^>]*href="tel:([^"]+?)"#';
                if (preg_match($pattern, $storeInfoListMatch[1], $phoneMatch)) {
                    $eStore->setPhoneNormalized(preg_replace('#\+33#', '0', $phoneMatch[1]));
                }

                $eStore->setStreetAndStreetNumber($aAddress['streetAddress'], 'fr')
                    ->setZipcode($aAddress['postalCode'])
                    ->setCity(ucwords(strtolower($aAddress['addressLocality'])))
                    ->setWebsite($singleStoreUrl);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}