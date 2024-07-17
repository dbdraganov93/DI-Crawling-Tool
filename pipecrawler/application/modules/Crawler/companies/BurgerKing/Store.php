<?php

/**
 * Storecrawler für Burger King (ID: 28990)
 */
class Crawler_Company_BurgerKing_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.burgerking.de/';
        $searchUrl = $baseUrl . 'kingfinder';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<h3[^>]*>\s*Restaurantliste(.+?)<\/section#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<tr[^>]*data-link="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $searchUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)\s*<\/p>\s*<p[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#class="tel"[^>]*>(.+?)<\/tr#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#class="openingtimes"[^>]*>(.+?)<\/table#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE);
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
