<?php

/**
 * Storecrawler für Intersport (ID: 316)
 *
 * Class Crawler_Company_Intersport_Store
 */
class Crawler_Company_Intersport_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.intersport.de/';
        $searchUrl = $baseUrl . 'MerchantSearch/getMerchants/?nfbSearch=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 5);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="merchant-card[^>]*>(.+?)<\/a>\s*<\/div>\s*<\/div>\s*<\/div>#';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#>\s*([^,]+?,\s*)?(?<street>[^,>]+?)\s*,\s*(?<city>\d{5}\s+[^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<a[^>]*href="([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $urlMatch)) {
                    $eStore->setWebsite($urlMatch[1]);
                }

                $pattern = '#<a[^>]*href="tel:([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $pattern = '#<div[^>]*class="merchant-card--name"[^>]*>\s*([^<]+?)\s*<\/div#';
                if (preg_match($pattern, $singleStore, $titleMatch)) {
                    $eStore->setTitle($titleMatch[1]);
                }

                $pattern = '#<div[^>]*class="merchant-card--opening-hour"[^>]*>(.+?)<\/div>\s*<\/div#';
                if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }

                $eStore->setAddress($addressMatch['street'], $addressMatch['city']);

                $cStores->addElement($eStore);
            }

        }

        return $this->getResponse($cStores);
    }

}