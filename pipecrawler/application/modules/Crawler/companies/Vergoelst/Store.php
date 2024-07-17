<?php

/* 
 * Store Crawler für Vergoelst (ID: 28564)
 */

class Crawler_Company_Vergoelst_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.vergoelst.de/';
        $searchUrl = $baseUrl . 'haendlersuche/';
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        // Städte aus der Liste finden
        $pattern = '#<a href=[\'"]/(haendlersuche\?[^\'"]*)[\'"][^>]*>([^<]*)</a>#';
        if (!preg_match_all($pattern, $page, $cityMatches)) {
            throw new Exception('unable to get stores from list: ' . $searchUrl);
        }

        foreach ($cityMatches[0] as $key => $value) {
            $cityUrl = $baseUrl . str_replace(' ', '%20', $cityMatches[1][$key]);
            $aCityUrl = explode('=', $cityUrl);
            $aCityUrl[count($aCityUrl) - 1] = urlencode($aCityUrl[count($aCityUrl) - 1]);
            $cityUrl = implode('=', $aCityUrl);
            $cityName = trim($cityMatches[2][$key]);

            try {
                $sPage->open($cityUrl);
            } catch (Exception $e) {
                $this->_logger->err('couldn\'t open city url ' . $cityUrl);
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();

            // Leere Seiten ignorieren
            $pattern = '#Leider konnte kein Betrieb in Ihrer Nähe gefunden werden\.#i';
            if (preg_match($pattern, $page, $match)) {
                continue;
            }

            $crawledUrls = array();

            // Standorte aus Liste nehmen
            $pattern = '#<td[^>]*>\s*<a[^>]*href=\'[/]*([^\']+)\'#';
            if (!preg_match_all($pattern, $page, $matches)) {
                $this->_logger->err('unable to get stores for city "' . $cityName . '": ' . $cityUrl);
                continue;
            }

            foreach ($matches[0] as $key2 => $value2) {
                $storeUrl = $baseUrl . $matches[1][$key2];
                if (in_array($storeUrl, $crawledUrls)){
                    continue;
                }

                $crawledUrls[] = $storeUrl;
                // "spezielle" Seiten separat behandeln
                if (!preg_match('#\/apps\/#', $storeUrl)) {
                    $eStore = $this->_crawlSpecialStorePage($storeUrl);

                    if (!$eStore){
                        continue;
                    }
                } else {
                    $sPage->open($storeUrl);
                    $page  = $sPage->getPage()->getResponseBody();
                    $eStore = new Marktjagd_Entity_Api_Store();

                    // Nummer aus Link zur Anfahrtsbeschreibung
                    $pattern = '#oid=(.*?)$#';
                    if (!preg_match($pattern, $storeUrl, $match)) {
                        $this->_logger->err('unable to get store special number: ' . $storeUrl);
                        continue;
                    }

                    $eStore->setStoreNumber($match[1]);

                    // Untertitel
                    $pattern = '#<p[^>]*>Detailinformationen[^<]*</p>\s*<table[^>]*>\s*' .
                        '<tr[^>]*>\s*<td[^>]*>([^<]*)</td>#i';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setSubtitle(trim($match[1]));
                    }

                    // Adresse
                    $pattern = '#<td[^>]*>([^<]*)</td>\s*</tr>\s*<tr[^>]*>\s*<td[^>]*>\s*([0-9]{5})([^<]*)</td>#i';
                    if (!preg_match($pattern, $page, $match)) {
                        $this->_logger->err('unable to get store address: ' . $storeUrl);
                        continue;
                    }
                    $eStore->setStreetAndStreetNumber(trim($match[1]));
                    $eStore->setZipcode(trim($match[2]));
                    $eStore->setCity(trim($match[3]));

                    // Telefon
                    $pattern = '#>\s*Telefon([^<]*)<#i';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setPhoneNormalized($match[1]);
                    }

                    // Telefax
                    $pattern = '#>\s*Telefax([^<]*)<#i';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setFaxNormalized($match[1]);
                    }

                    // E-Mail-Adresse
                    $pattern = '#>\s*e-?Mail: <a href="mailto:([^"]*)"[^>]*>#i';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setEmail(trim($match[1]));
                    }

                    // Öffnungszeiten
                    $pattern = '#<td>\s*Öffnungszeiten:(.*?)</td>#i';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setStoreHoursNormalized($match[1]);
                    }

                    $pattern = '#<table[^>]*cellpadding=\'4\'[^>]*>(.+?)</table#';
                    if (preg_match($pattern, $page, $serviceListMatch)) {
                        $pattern = '#<b[^>]*>\s*(.+?)\s*<#';
                        $strService = '';
                        if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                            foreach ($serviceMatches[1] as $singleService) {
                                if (preg_match('#spezielle#', $singleService)) {
                                    continue;
                                }
                                if (strlen($strService)) {
                                    $strService .= ', ';
                                }
                                $strService .= preg_replace('#\,#', '', $singleService);
                            }
                        }
                        $pattern = '#(EC\-.+?|Eurocard.+?|Visa.+?|Mastercard.+?|Finanz.+?)\s*<#i';
                        if (preg_match_all($pattern, $serviceListMatch[1], $paymentMatches)) {
                            $eStore->setPayment(implode(', ', $paymentMatches[1]));
                        }

                        $eStore->setService($strService);
                    }
                }

                $cStores->addElement($eStore);
            } // Ende Standorte
        } // Ende Städte
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

    protected function _crawlSpecialStorePage($storeUrl) {
        $sPage = new Marktjagd_Service_Input_Page();
        try {
            $sPage->open($storeUrl);
        } catch (Exception $e) {
            $this->_logger->err('couldn\'t open store url ' . $storeUrl);
            return false;
        }

        $page = $sPage->getPage()->getResponseBody();
        $eStore = new Marktjagd_Entity_Api_Store();

        // Nummer aus Link zur Anfahrtsbeschreibung
        $pattern = '#<a[^>]*href="\/vcard\.php\?fbnr\=(.*?)"#';
        if (preg_match($pattern, $page, $match)) {
            $eStore->setStoreNumber($match[1]);
        }

        // Untertitel
        $pattern = '#<address[^>]*>\s*<strong[^>]*>(.+?)</strong>#';
        if (preg_match($pattern, $page, $match)) {
            $eStore->setSubtitle(trim(strip_tags(preg_replace('#\s*<br[^>]*>\s*#', ' ', $match[1]))));
        }

        // Adresse
        $pattern = '#<span[^>]*class="Address_Street"[^>]*>([^<]+)</span>\s*<br[^>]*>\s*' .
            '<span[^>]*class="Address_City"[^>]*>\s*' .
            '<span[^>]*class="Adress_ZipCode"[^>]*>([^<]+)</span>\s*' .
            '<span[^>]*class="Adress_CityName"[^>]*>([^<]+)</span>\s*' .
            '</span>#is';
        if (!preg_match($pattern, $page, $match)) {
            $this->_logger->err('unable to get special store address: ' . $storeUrl);
            return false;
        }
        $eStore->setStreetAndStreetNumber(trim($match[1]));
        $eStore->setZipcode(trim($match[2]));
        $eStore->setCity(trim($match[3]));

        // Telefon
        $pattern = '#<span[^>]*>\s*Tel\.:\s*</span>\s*' .
            '<span[^>]*>\s*<a[^>]*>([^<]+)</a>\s*</span>#';
        if (preg_match($pattern, $page, $match)) {
            $eStore->setPhoneNormalized($match[1]);
        }

        // Telefax
        $pattern = '#<span[^>]*>\s*Fax:\s*</span>\s*' .
            '<span[^>]*>([^<]+)</span>#';
        if (preg_match($pattern, $page, $match)) {
            $eStore->setFaxNormalized($match[1]);
        }

        // Öffnungszeiten
        $pattern = '#<strong[^>]*>\s*Öffnungszeiten:\s*</strong>\s*' .
            '((<br[^>]*>\s*<span[^>]*>[^<]+</span>\s*)+)#';
        if (preg_match($pattern, $page, $match)) {
            $eStore->setStoreHoursNormalized($match[1]);
        } // Ende Öffnungszeiten
        // Beschreibungstext steht unter "Service"
        $pattern = '#<div[^>]*class=.+?FbServices"[^>]*>(.+?)</div>\s*</div>#';
        if (preg_match($pattern, $page, $serviceListMatch)) {
            $pattern = '#<span[^>]*class="TextNode"[^>]*>\s*(.+?)\s*<#';
            $strService = '';
            if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                foreach ($serviceMatches[1] as $singleService) {
                    if (preg_match('#spezielle#', $singleService)) {
                        continue;
                    }
                    if (strlen($strService)) {
                        $strService .= ', ';
                    }
                    $strService .= preg_replace('#\,#', '', $singleService);
                }
            }
            $pattern = '#(EC\-.+?|Eurocard.+?|Visa.+?|Mastercard.+?|Finanz.+?)\s*<#i';
            if (preg_match_all($pattern, $serviceListMatch[1], $paymentMatches)) {
                $eStore->setPayment(implode(', ', $paymentMatches[1]));
            }

            $eStore->setService($strService);
        }

        return $eStore;
    }
}
