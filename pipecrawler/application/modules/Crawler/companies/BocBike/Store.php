<?php

/**
 * Store Crawler für Bikemax (ID: 29065) & B.O.C auf Bike (ID: 69713)
 */
class Crawler_Company_BocBike_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.boc24.de/';
        $searchUrl = $baseUrl . 'info/filialen';
        $bikemaxxLogo        = 'http://media1.marktjagd.de/geschaeft/Bikemax-Aachen-Krefelder-Strasse:1061532_221x62_orig.png';
        $bocLogo            = 'http://media2.marktjagd.de/geschaeft/Bikemax-Aachen-Krefelder-Strasse:1061531_117x92_orig.png';

        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+)"[^>]*>\s*<img[^>]*src="[^"]*kennenlernen_btn.jpg"#';
        if (!preg_match_all($pattern, $page, $sMatches)) {
            throw new Exception('unable to get stores: ' . $searchUrl);
        }

        foreach ($sMatches[1] as $s => $storeUrl) {
            $storeUrl = str_replace('http://www.boc24.de', '../', $storeUrl);
            $storeUrl = $baseUrl . preg_replace('#^(../)+#', '', $storeUrl);

            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            // Nummer aux URL
            $pattern = '#/([^/]+)$#';
            if (preg_match($pattern, $storeUrl, $match)) {
                $eStore->setStoreNumber(preg_replace('#^fahrrad-#', '', $match[1]));
            }

            if($storeUrl == 'http://www.boc24.de//info/fahrrad-viernheim-bikemax-filiale'){
                continue;
            }

            // Adresse
            $pattern = '#<strong[^>]*>Adresse:</strong>\s*<br[^>]*>' .
                '(\s*(Rheinlandhalle)\s*<br[^>]*>\s*)?' .
                '([^<]+)<br[^>]*>\s*' .
                '([^<]+(<strong>\s*<a[^>]*>[^<]+</a>\s*</strong>)?[^<]*<br[^>]*>\s*)?' .
                '([0-9]{5})\s+([^<]+)<#';
            if (!preg_match($pattern, $page, $match)) {
                $this->_logger->err('unable to get store address: ' . $storeUrl);
                continue;
            }
            $eStore->setStreetAndStreetNumber(trim($match[3]));
            $eStore->setZipcode(trim($match[6]));
            $eStore->setCity(trim($match[7]));

            // Logo anhand des store_number
            if ($companyId == 29065) {
                if (preg_match('#bikemax#', $eStore->getStoreNumber())) {
                    $eStore->setLogo($bikemaxxLogo);
                } else {
                    continue;
                }
            } else {
                if (preg_match('#bikemax#', $eStore->getStoreNumber())) {
                    continue;
                } else {
                    $eStore->setLogo($bocLogo);
                }
            }

            // Telefon
            $pattern = '#>Tel\.: ([^<]+)<#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setPhoneNormalized($match[1]);
            }

            // Geokoordinaten
            $pattern = '#<a [^>]*href="[^"]*ll=([0-9]{1,2}\.[0-9]+),([0-9]{1,2}\.[0-9]+)[^"]*"[^>]*>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setLatitude($match[1]);
                $eStore->setLongitude($match[2]);
            }

            // Infotext "Unsere Serviceleistungen"
            $pattern = '#<p[^>]*><strong[^>]*>(Unsere Serviceleistungen:)(.+?)</p>#';
            $services = '';
            if (preg_match($pattern, $page, $match)) {
                $services = preg_replace(array(
                    '#^\s*(<br[^>]*>\s*)+#i',
                    '#(\s*<br[^>]*>)\s*+$#i',
                    '#\s*•\s*#',
                ), array(
                    '',
                    '',
                    '',
                ), strip_tags($match[2], '<br>'));
            }

            $eStore->setService(implode(', ', preg_split('#<br[^>]*>#', $services)));

            if (preg_match('#parken#is', $eStore->getService())){
                $eStore->setParking('kostenfrei Parken');
            }

            // Öffnungszeiten
            $pattern = '#<strong>Öffnungszeiten:</strong>(.+?)<(img|strong)#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setStoreHoursNormalized($match[1]);
            }

            $pattern = '#<object id="thumbnail" [^>]*data="/([^"]+)"[^>]*>#';
            if (preg_match($pattern, $page, $match)) {
                $url = $baseUrl . $match[1];
                $sPage->open($url);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<iframe src="/([^"]+)"[^>]*></iframe>#';
                if (preg_match($pattern, $page, $match)) {
                    $url = $baseUrl . $match[1];
                    $sPage->open($url);
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#<a [^>]*class="pics"[^>]*>\s*' .
                        '<img [^>]*class="thumb"[^>]*>\s*' .
                        '<span[^>]*>\s*<img [^>]*src="/([^"]+)"[^>]*>\s*</span>\s*' .
                        '</a>#';
                    if (preg_match_all($pattern, $page, $matches)) {
                        $images = array();
                        foreach ($matches[1] as $img) {
                            $images[] = $baseUrl . $img;
                        }
                        $images = array_slice($images, 0, 3);
                        $eStore->setImage(implode(',', $images));
                    }
                }
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}