<?php

/**
 * Storecrawler für SeatsAndSofas (ID: 69990)
 */
class Crawler_Company_SeatsandSofas_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.seatsandsofas.de/';
        $storeListUrl = $baseUrl . 'megastores/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($storeListUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*title="Megastore[^>]*href="([^"]+?\/megastores\/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#ffnungszeiten(.+?)<\/p#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $eStore->setAddress(preg_replace('#\|.+#', '', $addressMatch[1]), $addressMatch[2]);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}