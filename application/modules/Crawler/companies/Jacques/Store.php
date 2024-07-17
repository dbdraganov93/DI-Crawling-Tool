<?php

/* 
 * Store Crawler für Jacques Weindepot (ID: 28947)
 */

class Crawler_Company_Jacques_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.jacques.de/';
        $searchUrl = $baseUrl . 'winedepots.php';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $weekdayEng = array (
            '#Mo#',
            '#Tu#',
            '#We#',
            '#Th#',
            '#Fr#',
            '#Sa#',
            '#So#'
        );

        $weekdayGer = array (
            'Mo',
            'Di',
            'Mi',
            'Do',
            'Fr',
            'Sa',
            'So'
        );

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        $pattern = '#<div [^>]*class="(Depot|Area)"[^>]*>\s*' .
            '<a [^>]*href="([^"]*depot/([0-9]+)(/[^"]*)?)"[^>]*>([^<]+)</a>\s*' .
            '</div>#';
        if (!preg_match_all($pattern, $page, $sMatches)) {
            throw new Exception('unable to get stores: ' . $searchUrl);
        }

        foreach ($sMatches[0] as $key => $value) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($sMatches[3][$key]);

            $storeUrl = preg_replace('#/XTCsid/.*?$#', '', $sMatches[2][$key]);
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            // Adresse
            if (preg_match('#<[^>]*itemprop="streetAddress"[^>]*>(.+?)<#', $page, $match)) {
                $eStore->setStreetAndStreetNumber(trim($match[1]));
            }

            if (preg_match('#<[^>]*itemprop="postalCode"[^>]*>(.+?)<#', $page, $match)) {
                $eStore->setZipcode(trim($match[1]));
            }

            if (preg_match('#<[^>]*itemprop="addressLocality"[^>]*>(.+?)<#', $page, $match)) {
                $eStore->setCity(trim($match[1]));
            }

            if (preg_match('#<[^>]*itemprop="telephone"[^>]*>(.+?)<#', $page, $match)) {
                $eStore->setPhoneNormalized(trim($match[1]));
            }

            if (preg_match('#<[^>]*itemprop="faxNumber"[^>]*>(.+?)<#', $page, $match)) {
                $eStore->setFaxNormalized(trim($match[1]));
            }

            // Öffnungszeiten
            if (preg_match_all('#itemprop="openingHours".*?datetime="([^"]+)"#', $page, $match)){
                $sOpenings = implode(',', $match[1]);
                $sOpenings = preg_replace($weekdayEng, $weekdayGer, $sOpenings);
                $eStore->setStoreHoursNormalized($sOpenings);
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
