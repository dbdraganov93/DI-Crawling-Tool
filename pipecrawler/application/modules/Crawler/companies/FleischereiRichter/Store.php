<?php

/**
 * Store Crawler für Fleischerei Richter (ID: 28554)
 */
class Crawler_Company_FleischereiRichter_Store extends Crawler_Generic_Company {

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.richter-fleischwaren.de/tl/';
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a href="([^"]+)"[^>]*>Filialen</a>#';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('unable to get storefinder-link: ' . $baseUrl);
        }
        $url = $baseUrl . $match[1];
        $sPage->open($url);
        $page = $sPage->getPage()->getResponseBody();

        // "Filialenverzeichnis" finden
        $pattern = '#<a href="([^"]+)"[^>]*>Filialenverzeichnis</a>#';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('unable to get "Filialenverzeichnis": ' . $url);
        }

        $url = $baseUrl . $match[1];
        $sPage->open($url);
        $page = $sPage->getPage()->getResponseBody();

        // PLZ-Regionen aus der Liste finden
        $pattern = '#<p[^>]*>Postleizahlen-Bereich wählen:</p>\s*' .
            '<form[^>]*>\s*<input[^>]*>\s*<select[^>]*>(.*?)</select>#';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('unable to list of regions: ' . $url);
        }
        $pattern = '#<option[^>]*>([0-9]+)</option>#';
        if (!preg_match_all($pattern, $match[1], $rMatches)) {
            throw new Exception('unable to regions from list: ' . $url);
        }

        foreach ($rMatches[1] as $r => $region) {
            if ($region == 3) {
                continue;
            }

            $post = array(
                'FORM_SUBMIT'	=> 'stores',
                'searchterm'	=> $region,
            );

            $adapter = new Zend_Http_Client_Adapter_Curl();
            $adapter->setCurlOption(CURLOPT_REFERER, $url);

            $oPage = $sPage->getPage();
            $client = $oPage->getClient();
            $client->setAdapter($adapter);
            $oPage->setClient($client);
            $oPage->setMethod('POST');
            $sPage->setPage($oPage);

            $sPage->open($url, $post);
            $page = $sPage->getPage()->getResponseBody();

            // Standorte finden
            $pattern = '#<div class="stores_item"[^>]*>\s*' .
                '<h2[^>]*>([^<]+)</h2>\s*' .
                '<div class="stores_item_column"[^>]*>(.*?)</div>\s*' .
                '<div class="stores_item_column"[^>]*>(.*?)</div>\s*' .
                '</div>#';
            if (!preg_match_all($pattern, $page, $sMatches)) {
                $this->_logger->err('unable to get stores in region "' . $region . '": ' . $url);
                continue;
            }

            foreach ($sMatches[0] as $key => $value) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $storeName		= trim($sMatches[1][$key]);
                $addressText	= trim($sMatches[2][$key]);
                $hoursText		= trim($sMatches[3][$key]);

                // Adresse
                $pattern = '#^([^<]+)<br[^>]+>\s*([0-9]{5})\s+([^<]+)<br[^>]*>#';
                if (!preg_match($pattern, $addressText, $match)) {
                    $this->_logger->err('unable to get addrees for store "' . $storeName . '" in region "' . $region . '": ' . $url);
                    continue;
                }
                $eStore->setStreetAndStreetNumber(trim($match[1]));
                $eStore->setZipcode($match[2]);
                $eStore->setCity(trim($match[3]));

                // Telefon
                $pattern = '#<br[^>]*>Telefon:([^<]+)<br[^>]*>#';
                if (preg_match($pattern, $addressText, $match)) {
                    $eStore->setPhoneNormalized($match[1]);
                }

                // Telefax
                $pattern = '#Telefax:(.*)$#';
                if (preg_match($pattern, $addressText, $match)) {
                    $eStore->setFaxNormalized($match[1]);
                }

                // Öffnungszeiten
                $pattern = '#<strong[^>]*>Öffnungszeiten:</strong>(.*?)<a#';
                if (preg_match($pattern, $hoursText, $match)) {
                    $eStore->setStoreHoursNormalized($match[1]);
                }

                $cStores->addElement($eStore);
            } // Ende Standorte
        } // Ende PLZ-Regionen

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
