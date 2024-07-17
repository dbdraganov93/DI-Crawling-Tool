<?php
/**
 * Store Crawler für Simply Market FR (ID: 72330)
 */

class Crawler_Company_SimplyMarketFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.simplymarket.fr/';
        $searchUrl = $baseUrl . '4-mon-magasin.htm';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<area[^>]*shape="poly"[^>]*href="\/([^"]+?mon-magasin\.htm)"#';
        if (!preg_match_all($pattern, $page, $storeAreaUrlMatches)) {
            throw new Exception($companyId . ': unable to get store area urls.');
        }

        $aStoreUrls = array();
        foreach ($storeAreaUrlMatches[1] as $singleStoreAreaUrl) {
            $storeAreaUrl = $baseUrl . $singleStoreAreaUrl;

            $sPage->open($storeAreaUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*id="geolocMagasin[^>]*>(.+?)</table#';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': no stores for ' . $storeAreaUrl);
                continue;
            }

            $pattern = '#<tr[^>]*>(.+?)<\/tr>#';
            if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores from list: ' . $storeAreaUrl);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#Simply\s*Market#';
                if (!preg_match($pattern, $singleStore)) {
                    $this->_logger->info($companyId . ': not a simply market.');
                    continue;
                }

                $pattern = '#<a[^>]*href="\/([^"]+?)"[^>]*>\s*choisir\s*ce\s*magasin#i';
                if (!preg_match($pattern, $singleStore, $urlMatch)) {
                    $this->_logger->err($companyId . ': unable to get store url: ' . $singleStore);
                    continue;
                }

                $aStoreUrls[] = $baseUrl . $urlMatch[1];
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreUrls as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<p[^>]*>\s*([^<]+?)\s*<br[^>]*>\s*(\d{5}\s+[A-Z][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<h4[^>]*>\s*horaires\s*<\/h4>\s*<ul[^>]*class="liste"[^>]*>(.+?)</ul#i';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'fra');
            }

            $pattern = '#tel\s*\.?\s*:?\s*<\/strong>\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#<h4[^>]*>\s*services\s*<\/h4>\s*<ul[^>]*class="liste"[^>]*>(.+?)</ul#i';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<span[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                ->setWebsite($singleStoreUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
