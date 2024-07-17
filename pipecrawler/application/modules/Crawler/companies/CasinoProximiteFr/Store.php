<?php
/**
 * Store Crawler für Casino Proximité FR (ID: 72375)
 */

class Crawler_Company_CasinoProximiteFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.casino-proximite.fr';
        $searchUrl = $baseUrl . '/ajax/stores';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $storeNumber => $storeInfos) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($storeNumber)
                ->setLatitude($storeInfos->coord->lat)
                ->setLongitude($storeInfos->coord->lng)
                ->setTitle($storeInfos->title)
                ->setStreetAndStreetNumber($storeInfos->address, 'fr')
                ->setZipcode($storeInfos->zip_code)
                ->setWebsite($baseUrl . $storeInfos->url)
                ->setCity(ucwords(strtolower($storeInfos->city)))
                ->setPhoneNormalized($storeInfos->phone);

            if (strlen($eStore->getWebsite())) {
                try {
                    $sPage->open($eStore->getWebsite());
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#<div[^>]*class="opening-hours"[^>]*>\s*<h[^>]*>\s*horaires\s*<[^>]*>(.+?)<\/div#i';
                    if (preg_match($pattern, $page, $storeHoursMatch)) {
                        $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'fr');
                    }

                    $pattern = '#<div[^>]*class="opening-hours"[^>]*>\s*<h[^>]*>\s*services\s*<[^>]*>(.+?)<\/div#i';
                    if (preg_match($pattern, $page, $serviceListMatch)) {
                        $pattern = '#<li[>]*>\s*([^<]+?)\s*<#';
                        if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                            $eStore->setService(implode(', ', $serviceMatches[1]));
                        }
                    }
                } catch (Exception $e) {
                    $this->_logger->info($companyId . ': unable to open detail site.');
                }
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $filename = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($filename);
    }
}