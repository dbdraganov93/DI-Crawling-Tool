<?php

/*
 * Store Crawler für Vobis (ID: 22242)
 */

class Crawler_Company_Vobis_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.vobis.com';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        $cStores = new Marktjagd_Collection_Api_Store();

        // Link zum Filialfinder finden
        $pattern = '#<a href="([^"]+)"[^>]*>\s*<strong[^>]*>Filialen</strong></a>#';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('unable to get finder-link: ' . $baseUrl);
        }

        $finderUrl = $match[1];
        $sPage->open($finderUrl);
        $page = $sPage->getPage()->getResponseBody();

        // Alle Standorte finden
        $pattern = '#<div class="tool">\s*' .
            '<div class="details">(.+?)</div>\s*' .
            '</div>#i';
        if (!preg_match_all($pattern, $page, $matches)) {
            throw new Exception('unable to get stores: ' . $finderUrl);
        }
        foreach ($matches[0] as $key => $sMatch) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $text = trim(preg_replace('#\s+#', ' ', $matches[1][$key]));

            // Adresse und Untertitel
            $pattern = '#<h3[^>]*>Adresse</h3>\s*' .
                '<p[^>]*>([^<]*)(<br[^>]*>([^<]*))?(<br[^>]*>([^<]+))?<br[^>]*>([^<]+)<br[^>]*>\s*([0-9]{5})\s+([^<]+)<#';
            if (!preg_match($pattern, $text, $match)) {
                $this->_logger->err('unable to get address from storetext "' . $text . '": ' . $finderUrl);
                continue;
            }
            if ('' != trim($match[1])) {
                $eStore->setSubtitle(trim($match[1]));
            }
            if ('' != trim($match[3])) {
                $eStore->setSubtitle($eStore->getSubtitle() . ' / ' . trim($match[3]));
            }

            $sStreet = preg_replace('#\s+\([^\)]+\)$#', '', trim($match[6]));
            $sStreet = preg_replace('#\s+/\s+[^0-9]+$#', '', $sStreet);

            $eStore->setStreetAndStreetNumber($sStreet);
            $eStore->setZipcode($match[7]);
            $eStore->setCity(trim($match[8]));

            // Kontaktdaten
            $pattern = '#<h3[^>]*>Kontakt</h3>\s*' .
                '<p[^>]*>\s*Tel:([^<]*)<br[^>]*>\s*Fax:([^<]*)(<br[^>]*>\s*Email:([^<]*))?<#';
            if (preg_match($pattern, $text, $match)) {
                $eStore->setPhoneNormalized(trim($match[1]));
                $eStore->setFaxNormalized(trim($match[2]));
                $eStore->setEmail(trim($match[4]));
            } else {
                $this->_logger->warn('unable to get contact details from storetext "' . $text . '": ' . $finderUrl);
            }


            // Öffnungszeiten
            $pattern = '#<h3[^>]*>Öffnungzeiten</h3>\s*' .
                '<p[^>]*>(.+?)</p>#';
            if (preg_match($pattern, $text, $match)) {
                $eStore->setStoreHoursNormalized($match[1]);
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
