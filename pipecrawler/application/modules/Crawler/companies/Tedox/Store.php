<?php

/*
 * Store Crawler für Tedox (ID: 67896)
 */

class Crawler_Company_Tedox_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.tedox.de/';
        $searchUrl = $baseUrl . 'shoplist/';
        $detailUrl = $baseUrl . 'shoplist/index/detail/?id=';
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $patternStoreList = '#ib_setMarker\(([0-9]{1,2}.*?)\)#s';
        if (!preg_match_all($patternStoreList, $page, $matchesStores)) {
            throw new Exception('couldn\'t find any stores on url ' . $searchUrl);
        }

        foreach ($matchesStores[1] as $keyStores => $matchStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $aStoreInfos = explode(',', $matchStore);

            $eStore->setLatitude(trim($aStoreInfos[0]));
            $eStore->setLongitude(trim($aStoreInfos[1]));
            $eStore->setTitle('tedox');
            $eStore->setSubtitle('Der Renovierungs-Discounter. Renovieren, dekorieren und alles für den Haushalt');

            $eStore->setStreetAndStreetNumber($aStoreInfos[3]);
            $eStore->setZipcodeAndCity(preg_replace('#\'#', '', $aStoreInfos[4]));
            $eStore->setStoreNumber(trim($aStoreInfos[5]));
            $eStore->setWebsite($detailUrl . $eStore->getStoreNumber());

            // Detailinfos auslesen
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();

            $patternImage = '#<p[^>]*class="product-image"[^>]*>\s*<img[^>]*src="(.*?)"[^>]*>#s';
            if (preg_match($patternImage, $page, $matchImage)) {
                $eStore->setImage($matchImage[1]);
            }

            $patternTel = '#<strong>Tel.:</strong>.*?([0-9]{1,}\s*[0-9]{1,})#s';
            if (preg_match($patternTel, $page, $matchTel)) {
                $eStore->setPhoneNormalized($matchTel[1]);
            }

            $patternFax = '#<strong>Fax:</strong>.*?([0-9]{1,}\s*[0-9]{1,})#s';
            if (preg_match($patternFax, $page, $matchFax)) {
                $eStore->setFaxNormalized($matchFax[1]);
            }

            $patternOpeningArea = '#<p[^>]*>\s*<strong>Öffnungszeiten:</strong>\s*(.*?)\s*</p>#s';
            if (preg_match($patternOpeningArea, $page, $matchOpeningArea)) {
                $eStore->setStoreHoursNormalized($matchOpeningArea[1]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
