<?php

/*
 * Store Crawler für GolfHouse (ID: 70983)
 */

class Crawler_Company_GolfHouse_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.golfhouse.de/';
        $searchUrl = $baseUrl . 'filialen';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aServices = array(
            'Leihschläger' => 'Leihschläger',
            'Übungsgrün' => 'Übungsgrün',
            'Sale' => 'Sale Bereich',
            'Logo' => 'Logo-Service',
            'Änderungsdienst' => 'Änderungsdienst',
            'XXL' => 'über 800m² Golf',
            'Fitting' => 'Fitting',
            'Abschlag' => 'Abschlag Anlage',
            'Werkstatt' => 'Werkstatt',
            'MyJoys' => 'MyJoys-Terminal'
        );

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#Filialen</a>\s*<ul[^>]*>(.+?)</ul#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $storeDetailUrl) {
            $storeUrl = $baseUrl . $storeDetailUrl;
            $eStore = new Marktjagd_Entity_Api_Store();

            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<p[^>]*class="address"[^>]*>(.+?)</p>#s';
            if (!preg_match_all($pattern, $page, $addressMatches)) {
                $this->_logger->err($companyId . ': unable to get store address for: ' . $storeUrl);
                continue;
            }

            $aAddress = preg_split('#\s*<[^>]*>\s*#', $addressMatches[1][0]);

            $pattern = '#unsere\s*leistungen(.+?)</div#si';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<img[^>]*alt="([^"]+?)"#';
                $strServices = '';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    foreach ($serviceMatches[1] as $singleService) {
                        if (preg_match('#parkplatz#i', $singleService) && !strlen($eStore->getParking())) {
                            $eStore->setParking('vor Ort');
                        }
                        if (!array_key_exists($singleService, $aServices)) {
                            continue;
                        }
                        if (strlen($strServices)) {
                            $strServices .= ', ';
                        }
                        $strServices .= $aServices[$singleService];
                    }
                }
            }

            $pattern = '#das\s*bieten\s*(.+?)unsere\s*partner#si';
            if (preg_match($pattern, $page, $textListMatch)) {
                $pattern = '#<p[^>]*>\s*(.+?)\s*</p>#';
                $strText = '';
                if (preg_match_all($pattern, $textListMatch[1], $textMatches)) {
                    foreach ($textMatches[1] as $singlePoint) {
                        if (!strlen(trim(strip_tags($singlePoint)))) {
                            continue;
                        }
                        if (strlen($strText . '<br/>' . strip_tags($singlePoint)) > 1000) {
                            break;
                        }
                        if (strlen($strText)) {
                            $strText .= '<br/>';
                        }
                        $strText .= strip_tags($singlePoint);
                    }
                }
            }

            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[1])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[1])))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[2]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[2]))
                    ->setPhone($sAddress->normalizePhoneNumber($aAddress[4]))
                    ->setEmail(preg_replace('#(E-Mail:\s+)#', '', $aAddress[6]))
                    ->setStoreHours($sTimes->generateMjOpenings($addressMatches[1][1]))
                    ->setService($strServices)
                    ->setText($strText);
            
            if (preg_match('#kurpfalz#i', $eStore->getStreet())) {
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[2])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[2])))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[3]))
                        ->setCity($sAddress->extractAddressPart('city', $aAddress[3]))
                        ->setSubtitle($aAddress[1])
                        ->setEmail(preg_replace('#(E-Mail:\s+)#', '', $aAddress[7]));
            }
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
