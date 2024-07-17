<?php

/**
 * Store Crawler für Musterhaus Küchen (ID: 29093)
 */
class Crawler_Company_MusterhausKuechen_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {

        $sUrl = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'http://www.musterhauskuechen.de/';
        $searchUrl = $baseUrl . 'fachgeschaeftssuche/search/?tx_machaendlersuche_pi2[search_text]=' . $sUrl::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $aUrls = $sUrl->generateUrl($searchUrl, $sUrl::$_TYPE_ZIP, 25);
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $urlZip) {
            $sPage->open($urlZip);
            $page = $sPage->getPage()->getResponseBody();

            // leere Seiten nicht crawlen
            $pattern = '#Ihre Suche nach "[0-9]{5}" lieferte kein eindeutiges Ergebnis\.#';
            if (preg_match_all($pattern, $page, $sMatches)) {
                continue;
            }

            // Alle Standorte finden
            $pattern = '#<tr[^>]*>\s*(.+?)</td>\s*</tr>#';
            if (preg_match_all($pattern, $page, $sMatches)) {
                foreach ($sMatches[1] as $s => $row) {
                    $eStore = new Marktjagd_Entity_Api_Store();

                    // Untertitel
                    $pattern = '#<td class="hp-name"[^>]*>(.+?)</td>#';
                    if (preg_match($pattern, $row, $match)) {
                        $pattern = array(
                            '#<br[^>]*>#',
                        );
                        $replacement = array(
                            ', ',
                        );
                        $eStore->setSubtitle(trim(strip_tags(preg_replace($pattern, $replacement, $match[1]))));
                    }

                    // Adresse, Telefon- und Fax-Nummer
                    $pattern = '#<td class="hp-address"[^>]*>\s*' .
                        '([0-9]{5})\s+([^<]+)<br[^>]*>\s*(.+?)\s*' .
                        'Telefon:([^<]+)(<br[^>]*>\s*Fax([^<]+))?' .
                        '</td>#';
                    if (!preg_match($pattern, $row, $match)) {
                        $this->_logger->err('unable to get address from store "' . $eStore->getSubtitle() . '": ' . $urlZip);
                    }

                    $eStore->setZipcode(trim($match[1]));
                    $eStore->setCity(trim($match[2]));

                    // Sonderbehandlung für "besondere" Adresse
                    if ($eStore->getZipcode() =='06108') {
                        $streetHelp = explode('<br>',$match[3]);
                        $streetHelp[1] = preg_replace('#<br[^>]*>#', '', $streetHelp[1]);
                        preg_match('#([A-Z]+)\s+(.+)#i',$streetHelp[0],$streetMatch);
                        $eStore->setStreetAndStreetNumber(trim($streetHelp[1] . $streetMatch[2]));
                    } else {
                        $eStore->setStreetAndStreetNumber(trim(preg_replace('#\s*<br[^>]*>\s*#', '', $match[3])));
                    }

                    $eStore->setPhoneNormalized($match[4]);
                    if ('' != $match[5]) {
                        $eStore->setFaxNormalized($match[6]);
                    }

                    // E-Mail-Adresse
                    $pattern = '#<a href="mailto:([^"]+)"[^>]*>#';
                    if (preg_match($pattern, $row, $match)) {
                        $eStore->setEmail(trim($match[1]));
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