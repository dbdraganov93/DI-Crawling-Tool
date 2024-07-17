<?php

/**
 * Store Crawler für Frick Fachmarkt (ID: 67302)
 */
class Crawler_Company_Frick_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.frick-fachmarkt.de/';
        $searchUrl = $baseUrl . 'index.php/standorte1';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<td[^>]*>\s*<a[^>]*href="\/(.+?\/detail[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any store links.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleLink) {
            $sPage->open($baseUrl . $singleLink);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#\/([0-9]+).+$#';
            if (!preg_match($pattern, $singleLink, $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get store number.');
                continue;
            }

            $pattern = '#filialfinder_content_nolabel"[^>]*>\s*<[^>]*>\s*([^<]{3,}?)\s*<#';
            if (!preg_match_all($pattern, $page, $addressMatches)) {
                $this->_logger->err($companyId . ' unable to get store address: ' . $storeNumberMatch[1]);
            }

            $pattern = '#Telefon.+?([0-9]+.+?)<.+?Fax.+?([0-9]+.+?)<#i';
            if (preg_match($pattern, $page, $contactMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($contactMatch[1]))
                        ->setFax($sAddress->normalizePhoneNumber($contactMatch[2]));
            }

            $pattern = '#Öffnungszeiten(.+?)<img#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }

            $pattern = '#<h3[^>]*>Service(.+?)</ul#s';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#href=".+?Service[^>]*>\s*(.+?)\s*<#';
                $strServices = '';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $strServices .= implode(', ', $serviceMatches[1]);
                }
            }

            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', strip_tags($addressMatches[1][1]))))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', strip_tags($addressMatches[1][1]))))
                    ->setCity($sAddress->extractAddressPart('city', strip_tags($addressMatches[1][2])))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', strip_tags($addressMatches[1][2])))
                    ->setService($strServices)
                    ->setStoreNumber($storeNumberMatch[1]);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
