<?php
/**
 * Store Crawler für Euromaster FR (ID: 72354)
 */

class Crawler_Company_EuromasterFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://centres.euromaster.fr/';
        $searchUrl = $baseUrl . 'search?query=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 5);

        $aStoreUrls = array();
        foreach ($aUrls as $singleUrl) {
            try {
                $sPage->open($singleUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#data-lf-url="\/(\d{7}-[^"]+?)"#';
                if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                    $this->_logger->info($companyId . ': unable to get any stores for: ' . $singleUrl);
                    continue;
                }

                $aStoreUrls = array_unique(array_merge($aStoreUrls, $storeUrlMatches[1]));
            } catch (Exception $e) {
                $this->_logger->info($companyId . ': unable to open ' . $singleUrl);
                continue;
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreUrls as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#onclick="dataLayer\.push\((.+?)\)"#';
            if (!preg_match($pattern, $page, $addressListMatch)) {
                $this->_logger->err($companyId . ': unable to get store address list.');
                continue;
            }

            $jAddress = json_decode(preg_replace('#\'#', '"', $addressListMatch[1]));

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<div[^>]*data-lf-outlet-hours[^>]*>\s*(.+?)\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'fr');
            }

            $pattern = '#(\d{7})-#';
            if (preg_match($pattern, $singleStoreUrl, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }

            $pattern = '#<a[^>]*target="_blank"[^>]*href="https:\/\/shop\.euromaster\.fr\/"[^>]*data-lf-tracking=\'{"bind":"click","category":"Services","label":"([^"]+?)"#';
            if (preg_match_all($pattern, $page, $sectionMatches)) {
                $eStore->setSection(implode(', ', array_unique($sectionMatches[1])));
            }

            $pattern = '#<a[^>]*target="_blank"[^>]*href="https:\/\/(shop|www)\.euromaster\.fr\/[^"]+"[^>]*data-lf-tracking=\'{"bind":"click","category":"Services","label":"([^"]+?)"#';
            if (preg_match_all($pattern, $page, $serviceMatches)) {
                $eStore->setService(implode(', ', array_unique($serviceMatches[2])));
            }

            $pattern = '#parking\s*:\s*<\/span>\s*<div[^>]*>\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $page, $parkingMatch)) {
                $eStore->setParking($parkingMatch[1]);
            }

            $pattern = '#"geo"\s*:\s*(\{[^\}]+?\}),#';
            if (preg_match($pattern, $page, $geoMatch)) {
                $jGeo = json_decode($geoMatch[1]);
                $eStore->setLatitude($jGeo->latitude)
                    ->setLongitude($jGeo->longitude);
            }

            $eStore->setStreetAndStreetNumber($jAddress->center->address, 'fr')
                ->setZipcode($jAddress->center->postcode)
                ->setCity($jAddress->center->city)
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}