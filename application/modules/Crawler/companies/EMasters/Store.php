<?php

/*
 * Store Crawler für E-Masters (ID: 69764)
 */

class Crawler_Company_EMasters_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.e-masters.de/';
        $searchUrl = $baseUrl . 'e-masters-fachbetriebe.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sDb = new Marktjagd_Database_Service_GeoRegion();

        $aZipCodes = $sDb->findZipCodesByNetSize(95);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipCodes as $singleZipCode) {
            $oPage = $sPage->getPage();
            $oPage->setMethod('POST');
            $sPage->setPage($oPage);

            $aParams = array(
                'staat' => 'D',
                'firma' => '',
                'plz_ort' => $singleZipCode,
                'radius' => '100',
                'staatstr' => 'D:5',
                'tx_vemeinvkeems_pi_umkreissuche[submit_button]' => 'Suchen'
            );

            try {
                $sPage->open($searchUrl, $aParams);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<tr[^>]*>(<td[^>]*>\s*<a[^<]*nohref.+?)</tr#';
                if (!preg_match_all($pattern, $page, $storeMatches)) {
                    $this->_logger->info($companyId . ': store in 100 km radius around: ' . $singleZipCode);
                }

                foreach ($storeMatches[1] as $singleStore) {
                    $pattern = '#<a\s*title[^>]*id="firma_([0-9]+?)"[^>]*>\s*(.+?)\s*<#';
                    if (!preg_match($pattern, $singleStore, $idTitleMatch)) {
                        $this->_logger->err($companyId . ': unable to get id or title for: ' . $singleStore);
                        continue;
                    }

                    $pattern = '#div\s*class="firma_detail[^>]*>(.+?)</div#';
                    if (!preg_match($pattern, $singleStore, $addressMatch)) {
                        $this->_logger->err($companyId . ': unable to get store address for: ' . $singleStore);
                        continue;
                    }

                    $aAddress = preg_split('#\s*<[^>]*br[^>]*>\s*#', $addressMatch[1]);
                    for ($i = 0; $i < count($aAddress); $i++) {
                        $aAddress[$i] = preg_replace('#.+?:\s*(.+?)#', '$1', strip_tags($aAddress[$i]));
                    }

                    $pattern = '#<td[^>]*>\s*D?-?([0-9]{5})\s*</td>\s*<td[^>]*>\s*(.+?)\s*<#';
                    if (!preg_match($pattern, $singleStore, $cityMatch)) {
                        $this->_logger->info($companyId . ': unable to get store city for: ' . $singleStore);
                        continue;
                    }


                    $eStore = new Marktjagd_Entity_Api_Store();

                    $eStore->setTitle($idTitleMatch[2])
                            ->setStoreNumber($idTitleMatch[1])
                            ->setStreetAndStreetNumber($aAddress[0])
                            ->setPhoneNormalized($aAddress[1])
                            ->setFaxNormalized($aAddress[2])
                            ->setEmail(preg_replace('#\(at\)#', '@', $aAddress[3]))
                            ->setWebsite($aAddress[4])
                            ->setZipcode($cityMatch[1])
                            ->setCity($cityMatch[2]);

                    $cStores->addElement($eStore, TRUE);
                }

                $pattern = '#<tr[^>]*><td[^>]*>\s*<a[^<]*href="([^"]+?)"#';
                if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                    $this->_logger->info($companyId . ': no e-masters-stores for zipcode: ' . $singleZipCode);
                    continue;
                }

                foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                    $storeDetailUrl = $baseUrl . $singleStoreUrl;
                    
                    $sPage->open($storeDetailUrl);
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#<div\s*id="firmenkontakt"[^>]*>(.+?)</div>\s*</div>#s';
                    if (!preg_match($pattern, $page, $storeInfoMatch)) {
                        $this->_logger->err($companyId . ': unable to get store infos: ' . $storeDetailUrl);
                        continue;
                    }

                    $pattern = '#<p\s*class="[^"]+?"[^>]*>(.+?)</p#';
                    if (!preg_match_all($pattern, $storeInfoMatch[1], $storeDetailMatches)) {
                        $this->_logger->err($companyId . ': unable to get store infos from list: ' . $storeDetailUrl);
                        continue;
                    }
                    for ($i = 0; $i < count($storeDetailMatches[1]); $i++) {
                        $storeDetailMatches[1][$i] = preg_replace('#.+?:\s*(.+?)#', '$1', strip_tags($storeDetailMatches[1][$i]));
                    }

                    $eStore = new Marktjagd_Entity_Api_Store();
                    
                    $pattern = '#<div\s*class="firmenlogo"[^>]*>\s*<img\s*src="([^"]+?([0-9]{1,})[^/]+?)"#';
                    if (preg_match($pattern, $page, $idLogoMatch)) {
                        $eStore->setLogo($idLogoMatch[1])
                                ->setStoreNumber($idLogoMatch[2]);
                    }

                    $pattern = '#div\s*class="firmen_experte"[^>]*>(.+?)</div#s';
                    if (preg_match($pattern, $page, $serviceListMatch)) {
                        $pattern = '#<li[^>]*>(.+?)</li>#';
                        if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                            $eStore->setService(implode(', ', $serviceMatches[1]));
                        }
                    }

                    $pattern = '#mapus_karte\.setCenter\(new\s*google\.maps\.LatLng\(\s*(.+?)\s*,\s*(.+?)\s*\)#';
                    if (preg_match($pattern, $page, $geoMatch)) {
                        $eStore->setLatitude($geoMatch[1])
                                ->setLongitude($geoMatch[2]);
                    }

                    $eStore->setTitle('E-Masters Fachbetrieb')
                            ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $storeDetailMatches[1][1])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $storeDetailMatches[1][1])))
                            ->setPhone($sAddress->normalizePhoneNumber($storeDetailMatches[1][3]))
                            ->setFax($sAddress->normalizePhoneNumber($storeDetailMatches[1][4]))
                            ->setEmail(preg_replace('#\(at\)#', '@', $storeDetailMatches[1][5]))
                            ->setWebsite($storeDetailMatches[1][6])
                            ->setZipcode($sAddress->extractAddressPart('zipcode', $storeDetailMatches[1][2]))
                            ->setCity($sAddress->extractAddressPart('city', $storeDetailMatches[1][2]));

                    $cStores->addElement($eStore, TRUE);
                }
            } catch (Exception $e) {
                continue;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
