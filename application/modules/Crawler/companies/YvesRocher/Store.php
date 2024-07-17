<?php

/*
 * Store Crawler für Yves Rocher (ID: 283)
 */

class Crawler_Company_YvesRocher_Store extends Crawler_Generic_Company
{
    protected $_baseUrl = 'http://storelocator.yves-rocher.com/';

    protected $_telPattern = array(
        '#[^0-9]#',
        '#^49#',
        '#^00#',
    );
    protected $_telReplacement = array(
        '',
        '0',
        '0',
    );

    public function crawl($companyId) {

        $searchUrl = $this->_baseUrl . 'de/europe/germany/';

        $cStores = new Marktjagd_Collection_Api_Store();
        $cStores = $this->_crawlRegion($searchUrl, 1, $cStores);
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param string $regionUrl
     * @param int $level
     * @param Marktjagd_Collection_Api_Store $cStores
     * @return Marktjagd_Collection_Api_Store
     * @throws Exception
     */
    protected function _crawlRegion($regionUrl, $level, $cStores)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($regionUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<h3[^>]*><img[^>]*>\s*Adresse</h3>#';
        if (preg_match($pattern, $page)) {
            return $this->_crawlStore($page, $regionUrl, $cStores);
        }

        // Alle Unter-Regionen dieser Region finden
        $pattern = '#\[([0-9]+),\s*"[^"]+",\s*([\.0-9]{5,}),\s*([\.0-9]{5,}),\s*"([^"]+)"\]#';
        if (!preg_match_all($pattern, $page, $rMatches)) {
            throw new Exception('unable to get sub-regions for region: ' . $regionUrl);
        }

        foreach ($rMatches[0] as $key => $value) {
            $regionUrl = $this->_baseUrl . str_replace('\/', '/', $rMatches[4][$key]);
            $this->_crawlRegion($regionUrl, $level + 1, $cStores);
        }

        return $cStores;
    }


    /**
     * @param string $page
     * @param string $storeUrl
     * @param Marktjagd_Collection_Api_Store $cStores
     * @return Marktjagd_Collection_Api_Store
     */
    protected function _crawlStore($page, $storeUrl, $cStores)
    {
        $eStore = new Marktjagd_Entity_Api_Store();
        // Geokoordinaten
        $pattern = '#var\s*lat\s*=\s*(.+?);\s*var\s*lng\s*=\s*(.+?);#';
        if (preg_match($pattern, $page, $match)) {
            $eStore->setLatitude($match[1]);
            $eStore->setLongitude($match[2]);
            // falsche Koordinaten korrigieren
            if ($eStore->getLatitude() < $eStore->getLongitude()) {
                $tmp				= $eStore->getLongitude();
                $eStore->setLongitude($eStore->getLatitude());
                $eStore->setLatitude($tmp);
            }
        }

        // Adresse
        $pattern = '#<span id="address"[^>]*>([^<]+)<br[^>]*>([^<]+<br[^>]*>)?\s*([0-9]{5})\s+([^<]+)<br[^>]*>#';
        if (!preg_match($pattern, $page, $match)) {
            $this->_logger->err('unable to get address: ' . $storeUrl);
            return $cStores;
        }

        $eStore->setStreetAndStreetNumber(trim($match[1]));
        $eStore->setZipcode($match[3]);
        $eStore->setCity(trim($match[4]));

        // Telefon
        $pattern = '#<span[^>]*>Telefon :</span>([^<]+)<#';
        if (preg_match($pattern, $page, $match)) {
            $eStore->setPhone(preg_replace($this->_telPattern, $this->_telReplacement, $match[1]));
            if (!preg_match('#^0#', $eStore->getPhone())) {
                $eStore->setPhone(null);
            }
        }

        // Öffnungszeiten
        $pattern = '#<div class="day"[^>]*>([^<]+)</div>\s*' .
            '<div class="earlymorning"[^>]*>([^<]+)</div>\s*' .
            '<div class="earlyafternoon"[^>]*>([^<]*)</div>#';
        if (preg_match_all($pattern, $page, $matches)) {
            $hours = array();
            for ($h = 0; $h < count($matches[0]); $h++) {
                $day = substr($matches[1][$h], 0, 2);
                if (preg_match('#geschlossen#i', $matches[2][$h])) {
                    continue;
                }
                $hours[] = $day . ' ' . trim($matches[2][$h]);
                if ('' != trim($matches[3][$h])) {
                    $hours[] = $day . ' ' . trim($matches[3][$h]);
                }
            }

            $eStore->setStoreHoursNormalized(preg_replace('#\s*-\s*#', '-', implode(',', $hours)));
        }

        $cStores->addElement($eStore);
        return $cStores;
    }
}
