<?php

/**
 * Store Crawler für Orterer (ID: 67668), Benz (ID: 67685), Sobi (ID: 67686), Fränky Getränkemärkte (ID: 67687)
 */
class Crawler_Company_Orterer_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.orterer.de/';
        if ($companyId == 67668) {
            $searchUrl = $baseUrl . 'orterer/maerkte_l_plz.php';
        } else if ($companyId == 67685) {
            $searchUrl = $baseUrl . 'benz/maerkte_l_plz.php';
        } else if ($companyId == 67686) {
            $searchUrl = $baseUrl . 'sobi/maerkte_l_plz.php';
        } else if ($companyId == 67687) {
            $searchUrl = $baseUrl . 'fraenky/maerkte_l_plz.php';
        } else {
            throw new Exception('unknown company id for crawler, company-id: ' . $companyId);
        }

        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        // Liste aller Standorte öffnen
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        // Standorte in der Liste finden
        $pattern = '#<table class="marktliste"[^>]*>(.+?)</table>#';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('unable to get store-list: ' . $searchUrl);
        }

        $pattern = '#<tr[^>]*>\s*(<td[^>]*>.+?</td>)\s*</tr>#';
        if (!preg_match_all($pattern, $match[1], $sMatches)) {
            throw new Exception('unable to get stores from list: ' . $searchUrl);
        }

        // Standorte einzeln erfassen
        foreach ($sMatches[0] as $key => $value) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $sContent = trim($sMatches[1][$key]);

            // Adresse
            $pattern = '#^<td[^>]*>([^<]+)<br[^>]*>([^<]+)<br[^>]*>\s*([0-9]{5})\s+([^<]+)</td>#';
            if (!preg_match($pattern, $sContent, $match)) {
                $this->_logger->err('unable to get store addresss from "' . $sContent . '": ' . $searchUrl);
            }

            $eStore->setStreetAndStreetNumber($match[2]);
            $eStore->setZipcode($match[3]);
            $eStore->setCity(preg_replace('#^(.+?)\s*-\s*.+?$#', '${1}', $match[4]));
            $sContent				= trim(str_replace($match[0], '', $sContent));

            // Telefon und -fax
            $pattern = '#<td[^>]*>Tel\.([^<]+)<br[^>]*>#';
            if (preg_match($pattern, $sContent, $match)) {
                $eStore->setPhoneNormalized($match[1]);
                $sContent		= trim(str_replace($match[0], '', $sContent));
            }

            // Geokoordinaten
            $pattern = '#<a href="[^"]+lat=([0-9]{1,2}\.[0-9]+)&lng=([0-9]{1,2}\.[0-9]+)">#';
            if (preg_match($pattern, $sContent, $match)) {
                $eStore->setLatitude($match[1]);
                $eStore->setLongitude($match[2]);
                $sContent			= trim(str_replace($match[0], '', $sContent));
            }

            // Bild
            $pattern = '#<a href="\.\./([^"]+)" rel="lightbox\[[0-9]+\]"[^>]*>#';
            if (preg_match($pattern, $sContent, $match)) {
                $eStore->setImage($baseUrl . $match[1]);
                $sContent = trim(str_replace($match[0], '', $sContent));
            }

            $sContent = strip_tags($sContent, '<br><br/><br />');

            // Öffnungszeiten
            $eStore->setStoreHoursNormalized($sContent);
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
