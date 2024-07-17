<?php
/**
 * Store crawler for Holzleitner (ID: 81114)
 */

class Crawler_Company_Holzleitner_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.holzleitner.de/';
        $searchUrl = $baseUrl . 'unsere-filialen';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#Unsere\s*Filialen<[^>]*>(.+?)<footer#is';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<div[^>]*class="branch-box"[^>]*>(.+?)<\/div>\s*<\/div>#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<a[^>]*href="([^"]+?)"[^>]*target="_self"#';
            if (preg_match($pattern, $singleStore, $urlMatch)) {
                $eStore->setWebsite($urlMatch[1]);
            }

            $pattern = '#<p[^>]*class="store-openings"[^>]*>(.+?)<\/p#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#>\s*([^\@<]+?(\d+)\@[^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $emailMatch)) {
                $eStore->setEmail($emailMatch[1])
                    ->setStoreNumber($emailMatch[2]);
            }

            $pattern = '#<p[^>]*class="branch-box--phone"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2]);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}