<?php

/* 
 * Store Crawler für Tom Tailor (ID: 279)
 */

class Crawler_Company_TomTailor_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {

        $baseUrl = 'https://www.tom-tailor.de/';
        $searchUrl = $baseUrl . '?view=servicecenter&ajax=true&module=storefinder&servicecenter=storefinder&submit=1&land=de&plz=';


        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $telPattern = array(
            '#[^0-9]#',
            '#^0049#',
            '#^49#',
            '#^00#',
        );
        $telReplacement = array(
            '',
            '0',
            '0',
            '0',
        );

        // Alle Postleitzahlenbereiche durchlaufen
        for ($zip = 1; $zip < 100; $zip++) {
            $zip = str_pad($zip, 2, '0', STR_PAD_LEFT);
            $sPage->open($searchUrl . $zip);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div id="resultsContainer">\s*<img[^>]*>\s*</div>\s*<br class="ClearBoth">#';
            if (preg_match_all($pattern, $page, $sMatches)) {
                $this->_logger->info('no stores available in this region: ' . $zip);
                continue;
            }

            // Alle Standorte finden
            $pattern = '#<td width="(25|33)%"[^>]*>(.+?)</td>#';
            if (!preg_match_all($pattern, $page, $sMatches)) {
                throw new Exception('unable to get stores for zipcode ' . $zip . '***: ' . $searchUrl . $zip);
            }

            foreach ($sMatches[2] as $s => $sText) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $sText = trim($sText);

                // Titel
                $pattern = '#^<b[^>]*>([^<]+)</b>#';
                if (preg_match($pattern, $sText, $match)) {
                    $eStore->setTitle(trim($match[1]));
                    $sText = trim(str_replace($match[0], '', $sText));
                }

                // Adresse
                $pattern = '#^(.*?)<[a-z]+[^>]*>([^<]+)<br[^>]*>\s*([0-9]{4,5})\s+([^<]+)</?[a-z]+[^>]*>#i';
                if (!preg_match($pattern, $sText, $match)) {
                    $this->_logger->err('unable to get address from text "' . $sText . '": ' . $searchUrl . $zip);
                    continue;
                }

                $eStore->setStreetAndStreetNumber(trim($match[2]));
                $eStore->setZipcode(str_pad($match[3], 5, '0', STR_PAD_LEFT));
                $eStore->setCity(trim($match[4]));
                $sText = trim(str_replace($match[0], '', $sText));

                // Untertitel vor der Adresse
                $pattern = '#<b[^>]*>([^<]+)</b>#';
                if (preg_match($pattern, $match[1], $match)) {
                    $eStore->setSubtitle(trim(preg_replace(array(
                        '#^\(#',
                        '#\)$#',
                    ), array(
                        '',
                        '',
                    ), $match[1])));
                }

                // Telefonnummer
                $pattern = '#Telefon:\s*<br[^>]*>([^<]+)<br[^>]*>#';
                if (preg_match($pattern, $sText, $match)) {
                    $eStore->setPhone(preg_replace($telPattern, $telReplacement, $match[1]));
                }

                $cStores->addElement($eStore);
            }
        }               
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}