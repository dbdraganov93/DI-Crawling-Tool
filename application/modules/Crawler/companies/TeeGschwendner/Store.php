<?php

/**
 * Store Crawler für Tee Gschwendner (ID: 29131)
 */
class Crawler_Company_TeeGschwendner_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.teegschwendner.de/';
        $sUrl = new Marktjagd_Service_Generator_Url();
        $searchUrl = $baseUrl . 'StoreLocator/search?lat=' . $sUrl::$_PLACEHOLDER_LAT
            . '&lng=' . $sUrl::$_PLACEHOLDER_LON
            . '&distance=500&country=DE';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();


        $aUrls = $sUrl->generateUrl($searchUrl, $sUrl::$_TYPE_COORDS, 2);

        foreach ($aUrls as $url) {
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*data-id="(.*?)"[^>]*>(.*?)</div>\s*<script#is';
            if (!preg_match_all($pattern, $page, $matchStores)) {
                throw new Exception($companyId . ': unable to get any pages.');
            }


            foreach ($matchStores[2] as $singleStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<div[^>]*class="[^"]*store\-details[^"]*"[^>]*>\s*<h2>(.*?)</h2>'
                    . '\s*<p>\s*(.*?)\s*<br\s*/>\s*(.*?)\s*<br\s*/>#is';
                if (preg_match($pattern, $singleStore, $matchAddress)) {
                    $eStore->setSubtitle($matchAddress[1]);
                    $eStore->setStreetAndStreetNumber($matchAddress[2]);
                    $eStore->setZipcodeAndCity($matchAddress[3]);

                    if (strlen($eStore->getZipcode()) == 4) {
                        $eStore->setZipcode(str_pad($eStore->getZipcode(), 5, '0', STR_PAD_LEFT));
                    }

                    if (preg_match('#Tel.\s*(.*?)<br#', $singleStore, $matchTel)) {
                        $eStore->setPhoneNormalized($matchTel[1]);
                    }

                    if (preg_match('#"mailto:(.*?)"#', $singleStore, $matchMail)) {
                        $eStore->setEmail($matchMail[1]);
                    }

                    if (preg_match('#href="(http.*?)"#', $singleStore, $matchWeb)) {
                        $eStore->setWebsite($matchWeb[1]);
                    }

                    if (preg_match('#<p>.*?ffnungszeiten\:(.*?)</p>#', $singleStore, $matchOpenings)) {
                        $eStore->setStoreHoursNormalized($matchOpenings[1]);
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
