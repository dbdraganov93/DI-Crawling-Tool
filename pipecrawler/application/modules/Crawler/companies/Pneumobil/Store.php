<?php

/**
 * Storecrawler für Pneumobil (ID: 67623)
 * 
 */
class Crawler_Company_Pneumobil_Store extends Crawler_Generic_Company
{   
    public function crawl($companyId) {        
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $baseUrl = 'https://www.pneumobil.info/';
        $searchUrl = $baseUrl . 'unternehmen/filialen/search.html';

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $sPage->open($searchUrl, array('option' => 'com_filialfinder', 'plz' => '01067'));
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<a href="/([^"]+)"[^>]*>' .
            '\s*<b>\s*[0-9]{5}\s+#is';

        if (!preg_match_all($pattern, $page, $sMatches)) {
            throw new Exception($companyId . ': could not find any stores ' . $searchUrl);
        }

        foreach ($sMatches[1] as $s => $storeUrl) {
            $storeUrl = $baseUrl . $storeUrl;

            // fix invalid chars
            $storeUrl = preg_replace(
                '#(/[0-9]{5}-[a-z]+)/([a-z0-9%]+-[0-9]+\.html)$#',
                '${1}-${2}',
                $storeUrl
            );

            $storeUrl = str_replace(array('ä', 'ö', 'ü', 'ß'), array('%C3%A4', '%C3%B6', '%C3%BC', '%C3%9F'), $storeUrl);

            try {
                $sPage->open($storeUrl);
            } catch (Zend_Http_Client_Adapter_Exception $adapterException) {
                $this->_logger->warn($adapterException->getMessage() . ': ' . $adapterException->getTraceAsString());
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            // Adresse
            $pattern = '#<b[^>]*>([^<]+)</b>\s*<br[^>]*>\s*' .
                '<b[^>]*>\s*([0-9]{5})\s*</b>\s*<b[^>]*>([^<]+)</b>\s*' .
                '</p>#';
            if (!preg_match($pattern, $page, $match)) {
                $this->_logger->err('unable to get store address: ' . $storeUrl);
            }

            $eStore->setStreetAndStreetNumber(trim($match[1]));
            $eStore->setZipcode($match[2]);
            $eStore->setCity(trim($match[3]));

            // Telefon
            $pattern = '#<span[^>]*>\s*Telefon:\s*</span>\s*<span[^>]*>([^<]+)</span>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setPhoneNormalized($match[1]);
            }

            // Telefax
            $pattern = '#<span[^>]*>\s*Telefax:\s*</span>\s*<span[^>]*>([^<]+)</span>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setFaxNormalized($match[1]);
            }

            // E-Mail-Adresse
            $pattern = '#<span[^>]*>\s*E-Mail:\s*</span>\s*<a[^<]*>([^<]+)</a>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setEmail(trim($match[1]));
            }

            // Infotext
            $pattern = '#<h2[^>]*>(Unser Service)</h2>\s*<ul[^>]*>(.+?)</ul>#';
            if (preg_match($pattern, $page, $match)) {
                $pattern = '#<li[^>]*>(.+?)</li>#';
                if (preg_match_all($pattern, $match[2], $matches)) {
                    $service = strip_tags(preg_replace('#<br[^>]*>#', ' ', implode(', ', $matches[1])));
                    $eStore->setText($match[1] . ':<br />' . $service);
                }
            }

            // Öffnungszeiten
            $pattern = '#<h3[^>]*>\s*Öffnungszeiten\s*</h3>\s*<p>(.+?)</p>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setStoreHoursNormalized($match[1]);
            } // Ende Öffnungszeiten

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}