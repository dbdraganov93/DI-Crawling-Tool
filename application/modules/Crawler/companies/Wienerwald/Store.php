<?php

/*7117
 * Store Crawler für Wienerwald (ID: 29028)
 */

class Crawler_Company_Wienerwald_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.wienerwald.de/';
        $searchUrl = $baseUrl . 'restaurants/deutschland';
       
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
               
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        // Finder öffnen und Standorte aus der Liste finden
        $pattern = '#<div class="restaurant-row"[^>]*>\s*<a href="/([^"]+)"[^>]*>#';
        if (!preg_match_all($pattern, $page, $sMatches)) {
            throw new Exception('unable to get stores from list: ' . $searchUrl);
        }

        foreach ($sMatches[1] as $s => $storeUrl) {
            $storeUrl = $baseUrl . $storeUrl;
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            // Nummer aus URL/
            $pattern = '#([^/]*)$#';
            if (preg_match($pattern, $storeUrl, $match)) {
                $eStore->setStoreNumber($match[1]);
            }

            // Adresse
            $pattern = '#<td[^>]*>Adresse</td>\s*<td[^>]*>([^<]+)(\s+<b>[^<]+</b>\s*)?<br[^>]*>\s*([0-9]{5})\s+([^<]+)<#';
            if (!preg_match($pattern, $page, $match)) {
                $this->_logger->err('unable to get store address: ' . $storeUrl);
                continue;
            }
            $eStore->setStreetAndStreetNumber(trim($match[1]));
            $eStore->setZipcode(trim($match[3]));
            $eStore->setCity(trim($match[4]));

            // Telefon
            $pattern = '#<td[^>]*>Telefon</td>\s*<td[^>]*>([^<]+)</td>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setPhoneNormalized($match[1]);
            }

            // Telefax
            $pattern = '#<td[^>]*>Fax</td>\s*<td[^>]*>([^<]+)</td>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setFaxNormalized($match[1]);
            }

            // E-Mail-Adresse
            $pattern = '#<td[^>]*>Email</td>\s*<td[^>]*>([^<]+)</td>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setEmail(trim($match[1]));
            }

            // Öffnungszeiten
            $pattern = '#<td[^>]*>Öffnungszeiten</td>\s*<td[^>]*>(.+?)</td>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setStoreHoursNormalized($match[1]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
