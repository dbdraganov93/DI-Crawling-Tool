<?php

/*
 * Store Crawler für ECE Center (ID: 71966)
 */

class Crawler_Company_EceCenter_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.ece.de/';
        $searchUrl = $baseUrl . 'center-projekte/shopping-1/#accordion_table_country_12';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<span[^>]*class="ecep-mapmarker[^>]*data-country-region="\[1,\d{1,2}\]"[^>]*>\s*<dt[^>]*>\s*<a[^>]*href="\/([^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = preg_replace('#shopping\/popup#', 'shopping', $singleStoreUrl);
            $sPage->open($baseUrl . $storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<title[^>]*>\s*([^<]+?)\s*-\s*ECE\s*<#';
            if (!preg_match($pattern, $page, $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get store title: ' . $singleStoreUrl);
                continue;
            }

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4,5}\s+[A-Z][^<]+?)\s*<#s';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#phone-link"[^>]*href="tel:[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#fax-link"[^>]*href="fax:[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $pattern = '#mail-link"[^>]*href="javascript[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#<a[^>]*target="_blank"[^>]*href="([^"]+?)"#';
            if (preg_match($pattern, $page, $websiteMatch)) {
                $eStore->setWebsite($websiteMatch[1]);
            }

            $eStore->setStreetAndStreetNumber($addressMatch[1])
                    ->setZipcodeAndCity($addressMatch[2])
                    ->setWebsite($baseUrl . $storeDetailUrl)
                    ->setTitle($titleMatch[1]);

            if (strlen($eStore->getZipcode()) < 5) {
                $eStore->setZipcode(str_pad($eStore->getZipcode(), 5, '0', STR_PAD_LEFT));
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
