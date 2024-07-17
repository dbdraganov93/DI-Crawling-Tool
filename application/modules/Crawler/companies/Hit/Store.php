<?php

/**
 * Store Crawler für HIT Markt (ID: 58)
 */
class Crawler_Company_Hit_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $baseUrl = 'https://www.hit.de/';
        $searchUrl = '1.0/api/store/search.json?address=01187';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($baseUrl . $searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        foreach ($jStores->list as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleStore->id);
            if ($singleStore->id === 100) {
                $eStore->setStreet('Straße Des 18. Oktober');
                $eStore->setStreetNumber('44');
                $eStore->setZipcode(04103);
            } else {
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleStore->street)));
                $eStore->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleStore->street)));
                $eStore->setZipcode($singleStore->zip);
            }

            $eStore->setCity($singleStore->city);
            $eStore->setPhone($sAddress->normalizePhoneNumber($singleStore->phone));
            $eStore->setLatitude($singleStore->latitude);
            $eStore->setLongitude($singleStore->longitude);
            $eStore->setWebsite($baseUrl . $singleStore->alias . '.html');

            $sPage->open($baseUrl . rawurlencode($singleStore->alias) . '.html');
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#Öffnungszeiten<\/h3>(.+?)<\/div>\s*<\/div>#i';
            if (!preg_match($pattern, $page, $tmp)) {
                $this->_logger->err('Company ID ' . $companyId . ': unable to get opening hours for HIT store in ' . $singleStore->city);
                continue;
            }
            $pattern = array('#<[^>]*>#', '#Uhr #');
            $replacement = array('', 'Uhr, ');
            $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace($pattern, $replacement, $tmp[1])));
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
