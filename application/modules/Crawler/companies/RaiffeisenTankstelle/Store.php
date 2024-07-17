<?php

/**
 * Store Crawler für RaiffeisenTankstelle (ID: 67657)
 */
class Crawler_Company_RaiffeisenTankstelle_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $baseUrl = 'http://www.agravis.biz/';
        $searchUrl = $baseUrl . 'energie/verbundtankstellensuche/verbundtankstellen_gesamt.php';
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<tr([^>]*)>(.+?)</tr>#';
        if (!preg_match_all($pattern, $page, $rowMatches)) {
            throw new Exception('unable to get table rows: ' . $searchUrl);
        }

        $headlines = array();
        foreach ($rowMatches[0] as $key => $v) {
            $eStore = new Marktjagd_Entity_Api_Store();

            // Daten der Tankstellen auflösen
            $pattern = '#<td[^>]*>(.*?)</td>#';
            if (!preg_match_all($pattern, $rowMatches[2][$key], $matches)) {
                $this->_logger->err('unable to get cells from row ' . ($key + 1) . ': ' . $searchUrl);
            }

            $pattern = '#<td[^>]*>\s*<span[^>]*>([^<]+)</span>\s*</td>#';
            if (preg_match_all($pattern, $rowMatches[2][$key], $matchesHead)) {

                if ($key == 0) {
                    foreach ($matchesHead[1] as $matchHead) {
                        $headlines[] = $matchHead;
                    }
                }

                continue;
            }

            $value = $matches[1][0];
            $pattern = '#^<span[^>]*>(([^<]+)<br[^>]*>)?([^<]+)<br[^>]*>\s*([0-9]{5})\s+([^<]+)</span>#s';
            if (!preg_match($pattern, $value, $match)) {
                $this->_logger->err('unable to get address from cell-value "' . $value . '" in row ' . ($key + 1) . ': ' . $searchUrl);
                continue;
            }

            if ($match[1]) {
                $eStore->setSubtitle(trim($match[2]));
            }

            $eStore->setStreetAndStreetNumber(trim($match[3]));
            $eStore->setZipcode(trim($match[4]));
            $eStore->setCity(trim($match[5]));

            $value = str_replace($match[0], '', $value);

            // Website
            $pattern = '#<a href=\'([^\']+)\'[^>]*>Internet</a>#';
            if (preg_match($pattern, $value, $match)) {
                $eStore->setWebsite(trim($match[1]));
                $value = str_replace($match[0], '', $value);
            }

            // E-Mail-Adresse
            $pattern = '#<a href=\'mailto:([^\']+)\'[^>]*>E-Mail</a>#';
            if (preg_match($pattern, $value, $match)) {
                $eStore->setEmail(trim($match[1]));
            }

            // Öffnungszeiten (nur wenn 24h nen Haken hat)
            if ('x' == strtolower(trim(strip_tags($matches[1][1])))) {
                $eStore->setStoreHoursNormalized('Mo-So 00:00-24:00');
            }

            // Service aus den weiteren Zeilen
            $service = array();
            for ($c = 2; $c < count($matches[1]); $c++) {
                if ('x' == strtolower(trim(strip_tags($matches[1][$c])))) {
                    $service[] = str_replace('- ', '-', $headlines[$c]);
                }
            }
            if (count($service)) {
                $eStore->setService(implode(', ', $service));
            }

            $cStores->addElement($eStore);
        } // Ende Standorte

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId, false);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
