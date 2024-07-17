<?php

/*
 * Store Crawler für Reiff Reifen (ID: 69189)
 */

class Crawler_Company_Reiff_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.reiff-reifen.de/';
        $searchUrl = $baseUrl . 'de/standortsuchergebnis.html'
                . '?ort='
                . '&plz='
                . '&land=de'
                . '&radius=100'
                . '&latitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
                . '&longitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
                
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5);

        $aDetailUrls = array();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#href="\/(de/REIFF-Standorte/standort-[0-9]+[^"]+?)"#i';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                continue;
            }
            foreach ($storeUrlMatches[1] as $singleUrlMatch) {
                if (!in_array($baseUrl . $singleUrlMatch, $aDetailUrls)) {
                    $aDetailUrls[] = $baseUrl . $singleUrlMatch;
                }
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aDetailUrls as $storeDetailUrl) {
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            if (!preg_match('#<img[^>]*src="/gfx/icon/locale.png"[^>]*>\s*<p>(.+?)</p>#', $page, $match)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
            }

            $addressLines = preg_split('#<br[^>]*>#', $match[1]);

            $eStore = new Marktjagd_Entity_Api_Store();

            if (preg_match('#href="tel:([^"]+)"#', $page, $match)) {
                $eStore->setPhoneNormalized($match[1]);
            }

            if (preg_match('#href="mailto:([^"]+)"#', $page, $match)) {
                $eStore->setEmail(trim($match[1]));
            }

            if (preg_match_all('#<div[^>]*class="p-block"[^>]*>\s*<p>([^<]+)</p>#', $page, $match)) {
                $eStore->setPayment(implode(', ', $match[1]));
            }

            if (preg_match('#ffnungszeiten</span>(.+?)Termin<#', $page, $match)) {
                $eStore->setStoreHoursNormalized(preg_replace('#Uhr\s*<br[^>]*>#', ',', $match[1]));
            }

            if (preg_match('#<ul[^>]*>\s*<span[^>]*>Serviceleistung:</span>\s*<ul[^>]*>(.+?)</ul>#', $page, $match)) {
                if (preg_match_all('#<li[^>]*>(.+?)</li>#', $match[1], $submatch)) {
                    $eStore->setService(implode(', ', $submatch[1]));
                }
            }

            if (preg_match_all('#<ul[^>]*>\s*<span>(.+?\-Reifen):</span>#', $page, $match)) {
                $eStore->setSection(implode(', ', $match[1]));
            }

            $pattern = '#standort-(\d+)\/#';
            if (preg_match($pattern, $storeDetailUrl, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }

            $eStore->setAddress($addressLines[0], $addressLines[1])
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
