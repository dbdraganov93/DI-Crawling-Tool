<?php

/*
 * Store Crawler für Agravis (ID: 71690)
 */

class Crawler_Company_Agravis_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.agravis.de/';
        $searchUrl = $baseUrl . 'de/system/getdata/getdata.jsp?q=48';
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#([0-9]{5}.+?)\n#s';
        if (!preg_match_all($pattern, $page, $storePositionMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $sPage = new Marktjagd_Service_Input_Page();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storePositionMatches[1] as $singlePosition) {
            $storeListUrl = $baseUrl . 'de/system/ausgabestandorte/ausgabestandorte.jsp?Suche=' . urlencode($singlePosition) . '&zeige=1&radius50=1&Maerkte=1';
            usleep(15000000);
            try {
                $sPage->open($storeListUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<div[^>]*map-ergebnisdetail[^>]*>(.+?)</div>#s';
                if (!preg_match_all($pattern, $page, $storeMatches)) {
                    continue;
                }
                foreach ($storeMatches[1] as $singleStore) {
                    $eStore = new Marktjagd_Entity_Api_Store();
                    $pattern = '#<p[^>]*>(.+?)</p>#';
                    if (!preg_match_all($pattern, $singleStore, $storeInfoMatches)) {
                        $this->_logger->err($companyId . ' unable to get any store infos: ' . $singleStore);
                        continue;
                    }

                    $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeInfoMatches[1][1]);
                    $aContact = preg_split('#\s*<br[^>]*>\s*#', $storeInfoMatches[1][2]);

                    foreach ($storeInfoMatches[1] as $singleInfo) {
                        if (preg_match('#^Mo#', $singleInfo)) {
                            $eStore->setStoreHours($sTimes->generateMjOpenings($singleInfo));
                            continue;
                        }
                        if (substr_count($singleInfo, ',') >= 3) {
                            $eStore->setSection($singleInfo);
                            continue;
                        }
                        if (preg_match('#href="([^"]+?)"#', $singleInfo, $urlMatch)) {
                            $eStore->setWebsite($urlMatch[1]);
                        }
                    }

                    $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                            ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                            ->setPhone($sAddress->normalizePhoneNumber($aContact[0]))
                            ->setFax($sAddress->normalizePhoneNumber($aContact[1]))
                            ->setStoreNumber($eStore->getHash());

                    if (!preg_match('#raiffeisen#i', $storeInfoMatches[1][0])) {
                        $eStore->setSubtitle($storeInfoMatches[1][0]);
                    }

                    $cStores->addElement($eStore);
                }
            } catch (Exception $e) {
                $this->_logger->info($companyId . ': unable to open page: ' . $storeListUrl);
                continue;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
