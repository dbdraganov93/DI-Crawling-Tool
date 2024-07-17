<?php

/**
 * Store Crawler für McPaper (ID: 350)
 */
class Crawler_Company_McPaper_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.mcpaper.de/';
        $searchUrl = $baseUrl . 'index.php?id=66&no_cache=1';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $params = array(
            'strfnd'	=> 'senden',
            'x'			=> 0,
            'y'			=> 0,
        );

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        // Postleitzahlenbereiche durchgehen:
        for ($plz = 0; $plz <= 9; $plz++) {
            $params['zip'] = $plz;

            $sPage->open($searchUrl, $params);
            $page = $sPage->getPage()->getResponseBody();
            // Adressen parsen:
            if (!preg_match_all('#<tr class="filialfinder_[^"]+">\s*<td>(.*?)</td>\s*<td>(.*?)</td>\s*<td>(.*?)</td>\s*<td>(.*?)</table>#i', $page, $matches)) {
                $this->_logger->err('page of zipcode ' . $plz . ' has no addresses: ' . $searchUrl);
                continue;
            }

            foreach ($matches[0] as $key => $match) {
                $eStore = new Marktjagd_Entity_Api_Store();

                // Adresse
                $eStore->setCity(trim($matches[3][$key]));
                $eStore->setZipcode(trim($matches[2][$key]));
                $eStore->setStreetAndStreetNumber(trim($matches[1][$key]));

                // Öffnungszeiten
                $pattern = '#<tr><td>([a-z]{2})</td><td>([0-9]{1,2}:[0-9]{2}) bis ([0-9]{1,2}:[0-9]{2})</td></tr>#i';
                if (!preg_match_all($pattern, $matches[4][$key], $times)) {
                    $this->_logger->warn($companyId . ': cant\'s get opening-times (' . $key . ') zipcode: ' . $eStore->getZipcode());
                } else {
                    $hours = array();
                    for ($k = 0; $k < count($times[0]); $k++) {
                        array_push($hours, $times[1][$k] . ' ' . $times[2][$k] . ' - ' . $times[3][$k]);
                    }
                    if (count($hours)) {
                        $eStore->setStoreHoursNormalized(implode(',', $hours));
                    }
                }

                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
