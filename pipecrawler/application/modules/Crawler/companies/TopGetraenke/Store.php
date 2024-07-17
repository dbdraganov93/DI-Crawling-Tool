<?php

/**
 * Store Crawler für Top Getränke (ID: 67680)
 */
class Crawler_Company_TopGetraenke_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.topgetraenke.de/';
        $searchUrl = $baseUrl . 'maerkte.php?action=1&umkreis=1000&abschicken=Suchen&plzort=33332';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        // Standorte finden
        $pattern = '#<a href="/(markt\.php\?id=([0-9]+))"[^>]*>\s*Details\s*</a>#';
        if (!preg_match_all($pattern, $page, $sMatches)) {
            throw new Exception('unable to get stores for zipcode 33332 and radius 1000');
        }

        // Standorte einzeln erfassen
        foreach ($sMatches[0] as $key => $value) {
            $storeUrl = $baseUrl . $sMatches[1][$key];
            $storeNumber = $sMatches[2][$key];

            // Detailseite öffnen
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($storeNumber);

            // Untertitel
            $pattern = '#<h1[^>]*>TOP Getränke,\s*([^<]+)</h1>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setSubtitle(trim($match[1]));
            }

            // Adresse
            $pattern = '#<b[^>]*>Adresse:</b>([^<]+),\s*([0-9]{5})\s+([^<]+)<#';
            if (!preg_match($pattern, $page, $match)) {
                $this->_logger->err('unable to get store address: ' . $storeUrl);
            }

            $eStore->setStreetAndStreetNumber(trim($match[1]));
            $eStore->setZipcode(trim($match[2]));
            $eStore->setCity(trim($match[3]));

            // Telefon
            $pattern = '#<b[^>]*>Telefon:</b>([^<]+)<#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setPhoneNormalized($match[1]);
            }

            // Telefax
            $pattern = '#<b[^>]*>Fax:</b>([^<]+)<#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setFaxNormalized($match[1]);
            }

            // Öffnungszeiten
            $pattern = '#<b[^>]*>Öffnungszeiten (.*?)</p>#';

            $aTimes = array();

            if (preg_match_all($pattern, $page, $hMatches)) {
                foreach ($hMatches[1] as $hMatch) {
                    $aTimes[] = $sTimes->generateMjOpenings($hMatch);
                }
                $eStore->setStoreHoursNormalized(implode(',', $aTimes));
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
