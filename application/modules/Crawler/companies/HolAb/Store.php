<?php
/**
 * Store crawler for Hol Ab (ID: 22132)
 */

class Crawler_Company_HolAb_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $aCampaign = [
            21039,
            30853,
            30851,
            30900,
            29633,
            29646,
            29328,
            29633,
            29643,
            29640,
            29649,
            29393,
            29392,
            29386,
            29378,
            28844,
            28857,
            28844,
            28857,
            27305,
            21717,
            21684,
            21640,
            21683,
            21629,
            21614,
            21614,
            21682
        ];

        $baseUrl = 'https://holab.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $lastPage = FALSE;
        $cStores = new Marktjagd_Collection_Api_Store();
        for ($pageNo = 1; $pageNo < 100; $pageNo++) {
            $searchUrl = $baseUrl . 'firmen/branche.php?type=search&stichwort=&branche=&plz=&menuid=28&topmenu=3&letter=&search_plz=&search_firma=&search_telefon_fax=&search_email=&search_url=&search_strasse=&search_ort=&page=' . $pageNo;

            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<table[^>]*cellpadding="0"[^>]*cellspacing="0"[^>]*border="0"[^>]*width="100%"[^>]*style="padding-left:[^>]*10px;"[^>]*>(.+?)<\/table>#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores from ' . $searchUrl);
                continue;
            }

            if (count($storeMatches[1]) < 10) {
                $lastPage = TRUE;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setAddress($addressMatch[1], $addressMatch[2]);

                if (preg_match('#Tel\.:\s*([^<]+?)<#', $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                if (preg_match('#ffnungszeiten(.+?)<\/td>#s', $singleStore, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized(preg_replace(['#[^A-Z0-9:\s/<>]#i', '#--#'], '-', $storeHoursMatch[1]));
                }

                if (in_array($eStore->getZipcode(), $aCampaign)) {
                    $eStore->setDistribution('campaign');
                }

                $cStores->addElement($eStore);
            }

            if ($lastPage) {
                break;
            }
        }

        return $this->getResponse($cStores);
    }
}