<?php

/**
 * Store Crawler für Barrique (ID: 68886)
 */
class Crawler_Company_Barrique_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.barrique.com';
        $searchUrl = $baseUrl . '/Ladengeschaefte.5.0.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="csc-textpic-text"[^>]*>\s*(<div[^>]*>.+?</div>)?(.+?)</div>#';

        if (!preg_match_all($pattern, $page, $match)){
            throw new Exception('no stores found, please check: ' . $searchUrl);
        }

        // Standortbilder
        $pattern = '#<dt>\s*(<a[^>]*href="([^"]+)"[^>]*>)?\s*<img[^>]*src="([^"]+)"[^>]*>.*?</dt>'
            . '\s*<dd[^>]*class="csc-textpic-caption"[^>]*>([^<]+)</dd>#';
        preg_match_all($pattern, $page, $iMatch);

        foreach ($match[2] as $storeVal) {
            $storeVal = preg_replace('#<p[^>]*>#', '<br>', $storeVal);
            $storeVal = preg_replace('#</p>#', '', $storeVal);
            $storeVal = preg_replace('#<h4>.+?</h4>#', '', $storeVal);
            $storeVal = preg_replace('#<\/?strong>#', '', $storeVal);

            $storeAr = preg_split('#<br[^>]*>#', $storeVal);

            // Blöcke mit weniger als 3 Zeilen nicht relevant
            if (count($storeAr) <= 3) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $fallbackStreet = '';

            foreach ($storeAr as $storeLine) {
                // Zeilen durchlaufen und Merkmal finden
                $storeLine = trim($storeLine);

                if (preg_match('#ffnungszeiten#', $storeLine)) {
                    continue;
                }

                if (preg_match('#[^0-9]{3,}.+?[0-9]#', $storeLine, $match) && !$eStore->getStreet()) {
                    $eStore->setStreetAndStreetNumber($storeLine);
                }

                if (preg_match('#[0-9]{5}\s+[^0-9]{3,}#', $storeLine, $match) && !$eStore->getCity()) {
                    $eStore->setZipcodeAndCity($storeLine);
                }

                if (preg_match('#Telefon#i', $storeLine, $match) && !$eStore->getPhone()) {
                    $eStore->setPhoneNormalized($storeLine);
                    continue;
                }

                if (preg_match('#Fax#i', $storeLine, $match) && !$eStore->getFax()) {
                    $eStore->setFaxNormalized($storeLine);
                    continue;
                }

                if (preg_match('#<a[^>]*class="mail"[^>]*>(.+?)</a>#i', $storeLine, $match)
                    && !$eStore->getEmail()) {
                    $eStore->setEmail(preg_replace('#\(at\)#', '@', $match[1]));
                    continue;
                }
            }

            if (!$eStore->getStreet()) {
                // wurde keine Strasse gefunden, dann wird der erste Eintrag verwendet
                $eStore->setStreetAndStreetNumber($fallbackStreet);
            }

            // Bilder und ggf. Url zuweisem
            foreach ($iMatch[4] as $idx => $storeName){
                if (strpos($storeName, $eStore->getCity())) {
                    if (!preg_match('#^http#', $iMatch[3][$idx])){
                        $iMatch[3][$idx] = $baseUrl . '/' . $iMatch[3][$idx];
                    }
                    $eStore->setImage($iMatch[3][$idx]);

                    if (!preg_match('#^http#', $iMatch[4][$idx])){
                        $iMatch[4][$idx] = $baseUrl . '/' . $iMatch[4][$idx];
                    }
                    $eStore->setWebsite(str_replace(' ', '%20', $iMatch[4][$idx]));
                    break;
                }
            }

            $eStore->setSubtitle('Weinhandel ' . $eStore->getCity());

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
