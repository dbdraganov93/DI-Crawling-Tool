<?php

/**
 * Storecrawler für Picks Raus (ID: 69653)
 */
class Crawler_Company_PicksRaus_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.picksraus.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*id=\'bubble[^>]*>(.+?)<\/div>\s*<\/div>\s*<\/div#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception('unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#ffnungszeiten(.+?)\s*<\/div>\s*<\/div#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#setFiliale\(([^\)]+?)\)#';
            if (preg_match($pattern, $singleStore, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], preg_replace('#A-#', 'Augsburg ', $addressMatch[2]));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
