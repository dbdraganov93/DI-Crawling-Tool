<?php

/*
 * Store Crawler für Abele Optik (ID: 29057)
 */

class Crawler_Company_AbeleOptik_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://abele-optik.de';
        $searchUrl = $baseUrl . '/kontakt/filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $patternSection = '#<a [^>]*?href="[^"]*?maps.google.de/[^>]*?q=([^"]*?)".*?<div [^>]*?class="adr">(.*?)</div>\s*?</div>#i';

        if (!preg_match_all($patternSection, $page, $matchSections)) {
            throw new Exception('no stores found in finder page ' . $searchUrl);
        }

        foreach ($matchSections[2] as $x => $storeData) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $geolocations = preg_split('#,#', $matchSections[1][$x]);

            if ($geolocations[0] && $geolocations[1]) {
                $eStore->setLatitude($geolocations[0]);
                $eStore->setLongitude($geolocations[1]);
            }

            $pattern = '#<span [^>]*?class="postal-code"[^>]*?>([^<]*?)</span>#';
            if (!preg_match($pattern, $storeData, $match)) {
                $this->_logger->err(' zipcode not found in store no ' . $x);
                continue;
            }

            $eStore->setZipcode($match[1]);

            $pattern = '#<span [^>]*?class="locality"[^>]*?>([^<]*?)</span>#';
            if (!preg_match($pattern, $storeData, $match)) {
                $this->_logger->err(' city not found in store no ' . $x);
                continue;
            }

            $eStore->setCity($match[1]);

            $pattern = '#<div [^>]*?class="street-address"[^>]*?>.*?\,\s*([^<]*?)</div>#';
            if (!preg_match($pattern, $storeData, $match)) {
                $this->_logger->err(' street not found in store no ' . $x);
            }

            $eStore->setStreetAndStreetNumber($match[1]);

            $pattern = '#<div [^>]*?class="tel phone"[^>]*?>([^<]*?)</div>#';
            if (preg_match($pattern, $storeData, $match)) {
                $eStore->setPhoneNormalized($match[1]);
            }

            $pattern = '#<a[^>]*class="email"[^>]*>([^<]+)</a>#';
            if (preg_match($pattern, $storeData, $match)) {
                $eStore->setEmail($match[1]);
            }

            $pattern = '#ffnungszeiten:?(\s*<[^>]*>\s*)(.+?)\s*--#';
            if (preg_match($pattern, $storeData, $match)) {
                $eStore->setStoreHoursNormalized($match[2]);
            }

            $pattern = '#<a [^>]*?href="([^"]*?)"[^>]*?>[^<]*?zur\s*?Filialseite\s*?</a>#';
            if (preg_match($pattern, $storeData, $match)) {
                $website = $match[1];
                if (!preg_match('#^http#', $website)) {
                    $website = $baseUrl . '/unternehmen/' . $website;
                }

                $website = str_replace('http:', 'https:', $website);

                $eStore->setWebsite($website);
            }

            $cStores->addElement($eStore);
        }


        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
