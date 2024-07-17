<?php

/**
 * Store Crawler für Postbank (ID: 71656)
 */
class Crawler_Company_Postbank_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        Zend_Debug::dump('start time: ' . date('d.m.Y H:i'));
        $baseUrl = 'https://www.postbank.de/';
        $detailUrl = $baseUrl . 'dienste/gaa_filialsuche/filialsuche_detail.html?Identifier=';
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $count = 1;

        for ($i = 4; $i < 14; $i++) {
            $site = 1;
            $searchUrl = $baseUrl . 'dienste/gdata/api_js/V1/AddressSearches.asmx/'
                    . 'SearchBranchesCoordBasedRectangle?'
                    . 'SystemPartner=DeutscheBank&SecurityID=8oz6FcOUXlYZHPxPUadMfw%3D%3D&Channel=Postbank&'
                    . 'Branches=POST&Catchwords=&IsoCountryCode=DE&IsoLocale=de-DE&CoordFormatIn=GEODECIMAL_POINT&'
                    . 'Top=1000&OrderBy=DISTANCE&Page=' . $site . '&Addition=DatabaseID%3DYM_FILIALEN%26LocXForDistanceCalculation%3D'
                    . '13.78816%26LocYForDistanceCalculation%3D51.04587&FreeFilter=&MaxRadius=20000&Lux=' . $i
                    . '.0&Luy=58.9&Rlx=' . ($i + 3) . '.0&Rly=43.0&LocX=13.78816&LocY=51.04587';

            $oPage = $sPage->getPage();
            $oPage->setUseCookies(TRUE);
            $sPage->setPage($oPage);
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#(\{"GeoItems.+)<#s';
            if (!preg_match($pattern, $page, $storeMatch)) {
//                throw new Exception($companyId . ': unable to get any stores.');
                continue;
            }

            $jData = json_decode($storeMatch[1]);
            $maxSite = $jData->Paging->MaxPage;

            while ($site <= $maxSite) {
                $searchUrl = $baseUrl . 'dienste/gdata/api_js/V1/AddressSearches.asmx/'
                        . 'SearchBranchesCoordBasedRectangle?'
                        . 'SystemPartner=DeutscheBank&SecurityID=8oz6FcOUXlYZHPxPUadMfw%3D%3D&Channel=Postbank&'
                        . 'Branches=POST&Catchwords=&IsoCountryCode=DE&IsoLocale=de-DE&CoordFormatIn=GEODECIMAL_POINT&'
                        . 'Top=1000&OrderBy=DISTANCE&Page=' . $site . '&Addition=DatabaseID%3DYM_FILIALEN%26LocXForDistanceCalculation%3D'
                        . '13.78816%26LocYForDistanceCalculation%3D51.04587&FreeFilter=&MaxRadius=20000&Lux=' . $i
                        . '.0&Luy=58.9&Rlx=' . ($i + 3) . '.0&Rly=43.0&LocX=13.78816&LocY=51.04587';

                $sPage->open($searchUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#(\{"GeoItems.+)<#s';
                if (!preg_match($pattern, $page, $storeMatch)) {
                    continue;
                }

                $jData = json_decode($storeMatch[1]);

                foreach ($jData->AddressItems as $singleStore) {
                    $eStore = new Marktjagd_Entity_Api_Store();
                    $eStore->setStreet($sAddress->normalizeStreet($singleStore->BasicData->Address->Street))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($singleStore->BasicData->Address->HouseNo))
                            ->setCity($singleStore->BasicData->Address->City)
                            ->setZipcode($singleStore->BasicData->Address->Zip)
                            ->setPhone($sAddress->normalizePhoneNumber($singleStore->BasicData->Contact->Phone))
                            ->setFax($sAddress->normalizePhoneNumber($singleStore->BasicData->Contact->Fax))
                            ->setLatitude($singleStore->BasicData->Geo->YCoord)
                            ->setLongitude($singleStore->BasicData->Geo->XCoord);

                    $sPage->open($detailUrl . $singleStore->BasicData->Identifiers->YMID);
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#OpeningTimes(.+?)</dl#s';
                    if (preg_match($pattern, $page, $storeHoursMatch)) {
                        $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
                    }

                    $pattern = '#servicesList(.+?)</ul#s';
                    if (preg_match($pattern, $page, $serviceListMatch)) {
                        $pattern = '#<li[^>]*>\s*(.+?)\s*<#';
                        if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                            $eStore->setService(implode(', ', $serviceMatches[1]));
                        }
                    }

                    $pattern = '#<div\s*class="name">\s*(.+?[0-9]+)\s*</div>#';
                    if (!preg_match($pattern, $page, $storeNumberMatch)) {
                        continue;
                    }

                    $eStore->setStoreNumber(substr(md5($storeNumberMatch[1]), 0, 25));

                    if ($cStores->addElement($eStore, true)) {
                        Zend_Debug::dump('stores added: ' . $count++);
                    }

                }

                $site++;
            }
        }
        Zend_Debug::dump('end time: ' . date('d.m.Y H:i'));
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
