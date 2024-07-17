<?php

/**
 * Store Crawler für Microtrend (ID: 28657)
 */
class Crawler_Company_Microtrend_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.microtrend.de/';
        $searchUrl = $baseUrl . 'index.php?erweiterte-suche';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $params = array(
            'mt_search_address'		=> '88889',
            'mt_search_area'		=> '0',
            'mt_search_fulltext'	=> '',
        );

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $sPage->open($searchUrl, $params);
        $listPage = $sPage->getPage()->getResponseBody();

        $oPage = $sPage->getPage();
        $oPage->setMethod('GET');
        $sPage->setPage($oPage);

        $url = $searchUrl;

        // Schleife durch "weitere Ergebnisse"
        $pageNum = 0;
        while($url) {
            $pageNum++;
            if (1 != $pageNum) {
                $sPage->open($url);
                $listPage = $sPage->getPage()->getResponseBody();
            }

            $pattern = '#<a href="([^"]+)" class="showdetails">#';
            if (!preg_match_all($pattern, $listPage, $storeMatches)) {
                throw new Exception('unable to get stores: ' . $url);
            }

            foreach ($storeMatches[1] as $s => $storeUrl) {
                $sPage->open($storeUrl);
                $page = $sPage->getPage()->getResponseBody();

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setTitle('MICROTREND');

                // Untertitel
                $pattern = '#<h1[^>]*>([^<]*<br[^>]*>)?\s*<span class="bigger"[^>]*>([^<]*)</span>\s*</h1>#i';
                if (preg_match($pattern, $page, $match)) {
                    $eStore->setSubtitle(trim($match[2]));
                }

                // Straße
                $pattern = '#<span[^>]*>Straße[^<]*</span>\s*<p[^>]*>\s*([^<]*?[0-9]+[^<]*?)\s*</p>#';
                if (!preg_match($pattern, $page, $match)) {
                    $this->_logger->info('unable to get street: ' . $storeUrl);
                    continue;
                }
                $eStore->setStreetAndStreetNumber(trim(preg_replace('#<br[^>]*>#i', ' ', $match[1])));

                // Postleitzahl, Ort
                $pattern = '#<span[^>]*>PLZ, Ort[^<]*</span>\s*<p[^>]*>(D-)?([0-9]{5})\s*([^<]*)</p>#';
                if (!preg_match($pattern, $page, $match)) {
                    $this->_logger->info('unable to get zipcode and city: ' . $storeUrl);
                    continue;
                }

                $eStore->setZipcode($match[2]);
                $eStore->setCity(trim($match[3]));

                // E-Mail-Adresse
                $pattern = '#<span[^>]*>E-Mail[^<]*</span>\s*<p[^>]*>\s*<a[^>]*>([^<]*)</a></p>#';
                if (preg_match($pattern, $page, $match)) {
                    $eStore->setEmail(trim($match[1]));
                }

                // Webseite
                $pattern = '#<span[^>]*>Website[^<]*</span>\s*<p[^>]*>\s*<a href="([^"]*)"[^>]*>[^<]*</a>\s*</p>#';
                if (preg_match($pattern, $page, $match)) {
                    $eStore->setWebsite(trim($match[1]));
                    if (!preg_match('#^http#', $eStore->getWebsite())) {
                        $eStore->setWebsite('http://' . $eStore->getWebsite());
                    }
                }

                // Bild
                $pattern = '#<td class="mtimage"[^>]*>\s*<a href="([^"]*)" rel="lightbox"[^>]*>#';
                if (preg_match($pattern, $page, $match)) {
                    $eStore->setImage($match[1]);
                    if (!preg_match('#^http#', $eStore->getImage())) {
                        $eStore->setImage($baseUrl . $eStore->getImage());
                    }
                }

                // Serviceleistungen als Infotext
                $pattern = '#<li[^>]*>\s*<a href="([^"]*)">\s*<span[^>]*>Unsere Serviceleistungen</span>\s*</a>\s*</li>#';
                if (preg_match($pattern, $page, $match)) {
                    $url = $baseUrl . $match[1];
                    $sPage->open($url);
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#<h3[^>]*>([^<]*Serviceleistungen[^<]*)</h3>\s*.*?(<div[^>]*>\s*)?' .
                        '((<div class="service_listentry"[^>]*>.*?</div>\s*)+)#i';
                    if (preg_match_all($pattern, $page, $matches)) {
                        for  ($i = 0; $i < count($matches[1]); $i++) {
                            $title = trim($matches[1][$i]);
                            $pattern = '#<span>([^<]*)</span>#';
                            if (preg_match_all($pattern, $matches[3][$i], $items)) {
                                $eStore->setText(
                                    $eStore->getText()
                                        . ('' != $eStore->getText() ? '<br />' : '')
                                        . '<strong>' . $title . '</strong> ' . implode(', ', $items[1])
                                );
                            }
                        }
                    }

                    // Öffnungszeiten
                    $pattern = '#<li[^>]*>\s*<a href="([^"]*)">\s*<span[^>]*>Öffnungszeiten</span>\s*</a>\s*</li>#';
                    $url = $baseUrl . $match[1];

                    if (preg_match($pattern, $page, $match)) {
                        $sPage->open($url);
                        $page = $sPage->getPage()->getResponseBody();
                    }

                    $pattern = '#</table>\s*</div>(.*?)<p class="responsible"[^>]*>#';
                    if (preg_match($pattern, $page, $hMatch)) {
                        $eStore->setStoreHoursNormalized($hMatch[1]);
                    }

                    // Telefon, -fax und Geokoordinaten
                    $pattern = '#<li[^>]*>\s*<a href="([^"]*)">\s*<span[^>]*>Kontakt & Anfahrt</span>\s*</a>\s*</li>#';
                    if (preg_match($pattern, $page, $match)) {
                        $url = $baseUrl . $match[1];
                        $sPage->open($url);
                        $page = $sPage->getPage()->getResponseBody();


                        // Telefon
                        $pattern = '#<td[^>]*>\s*Telefon[^<]*</td>\s*<td[^>]*>\s*<b[^>]*>([^<]*)</b>\s*</td>#';
                        if (preg_match($pattern, $page, $match)) {
                            $eStore->setPhoneNormalized($match[1]);
                        }

                        // Telefax
                        $pattern = '#<td[^>]*>\s*Telefax[^<]*</td>\s*<td[^>]*>\s*<b[^>]*>([^<]*)</b>\s*</td>#';
                        if (preg_match($pattern, $page, $match)) {
                            $eStore->setFaxNormalized($match[1]);
                        }

                        // Geokoordinaten
                        $pattern = '#var point = new GLatLng\(([\.0-9]{3,}),\s*([\.0-9]{3,})\);#';
                        if (preg_match($pattern, $page, $match)) {
                            $eStore->setLatitude($match[1]);
                            $eStore->setLongitude($match[2]);
                        }
                    }
                }


                // Händler möchte nicht aufgenommen werden
                if (preg_match('#(allcomputer|olschewski|salamon)#i', $eStore->getSubtitle())) {
                    continue;
                }

                $cStores->addElement($eStore);
            } // end of Stores

            // Link "Weitere Ergebnisse" finden
            $pattern = '#<a href="([^"]*)"[^>]*><span>Weitere Ergebnisse</span></a>#';
            if (preg_match($pattern, $listPage, $match)) {
                $url = $baseUrl . $match[1];
            } else {
                $url = false;
            }

        } // Ende Seitenweise
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
