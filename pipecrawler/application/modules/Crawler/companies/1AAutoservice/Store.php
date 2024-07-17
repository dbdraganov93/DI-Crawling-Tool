<?php

/* 
 * Store Crawler für 1A Autoservice (ID: 28659)
 */

class Crawler_Company_1AAutoservice_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.go1a.de/';

        $sUrl = new Marktjagd_Service_Generator_Url();
        $searchUrl = $baseUrl . 'nc/werkstattsuche/?tx_tdgooglemap_addresses[action]=list&tx_tdgooglemap_addresses'
            . '[controller]=Address&tx_tdgooglemap_addresses[__referrer][extensionName]=TdGooglemap&tx_tdgooglemap_addresses'
            . '[__referrer][controllerName]=Address&tx_tdgooglemap_addresses[__referrer][actionName]=search'
            . '&tx_tdgooglemap_addresses[__hmac]=a%3A3%3A%7Bs%3A6%3A%22search%22%3Ba%3A5%3A'
            . '%7Bs%3A6%3A%22radius%22%3Bi%3A1%3Bs%3A7%3A%22country%22%3Bi%3A1%3Bs%3A3%3A%22zip%22%3Bi%3A1%3Bs%3A4%3A%22'
            . 'city%22%3Bi%3A1%3Bs%3A10%3A%22categories%22%3Ba%3A2%3A%7Bi%3A0%3Bi%3A1%3Bi%3A1%3Bi%3A1%3B%7D%7Ds%3A6%3A%22'
            . 'action%22%3Bi%3A1%3Bs%3A10%3A%22controller%22%3Bi%3A1%3B%7Dea11a07a994ace4fb7d039e88904db6231fb7dce'
            . '&tx_tdgooglemap_addresses[search][radius]=&tx_tdgooglemap_addresses[search][country]=germany'
            . '&tx_tdgooglemap_addresses[search][zip]=' . $sUrl::$_PLACEHOLDER_ZIP
            . '&tx_tdgooglemap_addresses[search][city]=Ort'
            . '&tx_tdgooglemap_addresses[search][categories]=';
        $sPage = new Marktjagd_Service_Input_Page();

        $aUrls = $sUrl->generateUrl($searchUrl, 'zipcode', 40);
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $urlZip) {
            $nextPage = $urlZip;

            // Schleife durch "weitere Ergebnisse"
            while ($nextPage) {
                $url = $nextPage;
                $sPage->open($url);
                $listPage = $sPage->getPage()->getResponseBody();

                // nächste Seite finden
                $nextPage = false;
                $pattern = '#<a [^>]*href="([^"]+)"[^>]*>\s*<img [^>]*src="[^"]+paginator_prev\.gif"[^>]*>\s*</a>#';
                if (preg_match($pattern, $listPage, $match)) {
                    $nextPage = $match[1];
                    if (!preg_match('#^https?://#i', $nextPage)) {
                        $nextPage = $baseUrl . $nextPage;
                    }
                }

                // keine Standorte vorhanden
                $pattern = '#<strong[^>]*>\s*(0 Treffer)\s*</strong>#';
                if (preg_match($pattern, $listPage, $match)) {
                    break;
                }

                // Liste aller Standorte finden
                $pattern = '#<ul [^>]*class="auflistung"[^>]*>(.+?)</ul>#';
                if (!preg_match($pattern, $listPage, $match)) {
                    break;
                }

                $pattern = '#<li[^>]*>\s*<a [^>]*href="([^"]+)"[^>]*>(.+?)</#';
                if (!preg_match_all($pattern, $match[1], $matches)) {
                    break;
                }

                // Standorte durchlaufen
                foreach ($matches[0] as $key => $value) {
                    $storeUrl	= $baseUrl . trim($matches[1][$key]);
                    $storeName	= trim(preg_replace(array('#[\s]+#', '#/\s*$#'), array(' ', ''), strip_tags($matches[2][$key])));

                    // Detailseite öffnen
                    $sPage->open($storeUrl);
                    $page = $sPage->getPage()->getResponseBody();

                    // Fehler abfangen
                    $pattern = '#<div[^>]*>\s*(An error occurred[^<]+)</div>#';
                    if (preg_match($pattern, $page, $match)) {
                        continue;
                    }

                    $eStore = new Marktjagd_Entity_Api_Store();
                    $eStore->setSubtitle($storeName);

                    // Geokoordinaten
                    $pattern = '#var latitude = ([\.0-9]{3,});\s*var longitude = ([\.0-9]{3,});#';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setLatitude($match[1]);
                        $eStore->setLongitude($match[2]);
                    }

                    // Straße
                    $pattern = '#<td[^>]*>\s*Straße:?\s*</td>\s*<td[^>]*>([^<]*)</td>#i';
                    if (!preg_match($pattern, $page, $match)) {
                        $this->_logger->err('unable to get street: ' . $storeUrl);
                        continue;
                    }

                    $eStore->setStreetAndStreetNumber(trim($match[1]));

                    // Postleitzahl und Ort
                    $pattern = '#<td[^>]*>Ort:?</td>[^<]*<td>([^<]*)</td>#i';
                    if (!preg_match($pattern, $page, $match)) {
                        $this->_logger->err('unable to get city: ' . $storeUrl);
                    }
                    $eStore->setZipcodeAndCity(trim($match[1]));

                    // Adresse überprüfen
                    if ('' == $eStore->getStreet()
                        || '' == $eStore->getZipcode()
                        || '' == $eStore->getCity()) {
                        $this->_logger->warn('uncompleted address "zipcode:' . $eStore->getZipcode()
                            . ',": city:' . $eStore->getCity() . ',street:' . $eStore->getStreet() . '": ' . $storeUrl);
                        continue;
                    }

                    // Telefon
                    $pattern = '#<td[^>]*>Telefon:?</td>\s*<td>([^<]*)</td>#i';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setPhoneNormalized($match[1]);

                    }
                    // Telefax
                    $pattern = '#<td[^>]*>Fax:?</td>\s*<td>([^<]*)</td>#i';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setFaxNormalized($match[1]);
                    }

                    // E-Mail-Adresse
                    $pattern = '#<td[^>]*>E-Mail:?</td>\s*<td>\s*<a href="mailto:([^"]*)"[^>]*>#';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setEmail(trim($match[1]));
                    }

                    // Webseite
                    $pattern = '#<td[^>]*>Web:?</td>\s*<td>\s*<a [^>]*href="([^"]*)"[^>]*>#i';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setWebsite(trim($match[1]));
                    }

                    $cStores->addElement($eStore);
                }
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}