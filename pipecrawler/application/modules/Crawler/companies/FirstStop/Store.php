<?php

/*
 * Store Crawler für First Stop (ID: 29123)
 */

class Crawler_Company_FirstStop_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.firststop.de/';
        $searchUrl = $baseUrl . 'filialen?z=61169&r=1000&s=';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#var\s*locations\s*=\s*\[([^;]+?)\];#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href=\\\"\\\/([^"]+?)\\\"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . preg_replace(array('#\\\#', '#\s#'), array('', '%20'), $singleStoreUrl);

            $this->_logger->info($companyId . ': opening ' . $storeDetailUrl);
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address - ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#fon\s*:*\s*<\/dt>\s*<dd[^>]*>\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#fax\s*:*\s*<\/dt>\s*<dd[^>]*>\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#ffnungszeiten(.+?)<\/dl>#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
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
