<?php

/* 
 * Store Crawler für Vögele Shoes CH (ID: 72216)
 */

class Crawler_Company_VoegeleShoesCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://voegele-shoes.com/';
        $searchUrl = $baseUrl . 'de/liste-der-filialen';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*class="shop-pkt[^"]*ui-draggable"[^>"]*>\s*<table[^>]*class="shop-table"[^>]*>(.+?)<\/li#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception ($companyId . ': unable to get stores.');
        }


        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<table[^>]*class="shop-hours"[^>]*>(.+?)<\/tbody#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#\@([^,]+?),([^,]+?),#';
            if (preg_match($pattern, $singleStore, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                    ->setLongitude($geoMatch[2]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2], 'CH');

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}