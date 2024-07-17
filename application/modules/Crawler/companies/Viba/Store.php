<?php

/**
 * Store Crawler für Viba Sweets (ID: 67688)
 */
class Crawler_Company_Viba_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.viba-sweets.de/';
        $searchUrl = $baseUrl . 'viba-filialen/filial-finder';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#div\s*id="orte"[^>]*>(.+?)</div#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#href="([^"]+?\/[^"]+?\/[^"]+?\/[^"]+?\/)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleUrl) {
            $storeUrl = $baseUrl . $singleUrl;

            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<h2[^>]*>adresse.+?<p[^>]*>\s*(.+?)\s*(</p|<br)#is';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . 'unable to get store address: ' . $storeUrl);
            }

            $aAddress = preg_split('#\s*\|\s*#', $addressMatch[1]);

            $pattern = '#kontaktdaten.+?<p[^>]*>(.+?)</p#is';
            if (!preg_match($pattern, $page, $contactMatch)) {
                $this->_logger->err($companyId . 'unable to get store contact: ' . $storeUrl);
            }

            $aContact = preg_split('#\s*<br[^>]*>\s*#', $contactMatch[1]);

            $pattern = '#ffnungszeiten.+?<p[^>]*>(.+?)</p#is';
            if (!preg_match($pattern, $page, $storeHoursMatch)) {
                $this->_logger->err($companyId . 'unable to get store hours: ' . $storeUrl);
            }

            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($aContact[0]))
                    ->setFax($sAddress->normalizePhoneNumber($aContact[1]))
                    ->setEmail(preg_replace('#E-Mail:\s*#', '', $aContact[2]))
                    ->setStoreHours($sTimes->generateMjOpenings(preg_replace(array('#täglich#', '#-\s*\&\s*Feiertage#'), array('Mo-So', 'tag'), $storeHoursMatch[1])));

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}