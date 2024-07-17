<?php

/**
 * Store Crawler für marché Restaurants (ID: 71667)
 */
class Crawler_Company_Marche_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.marche-restaurants.com/';
        $searchUrl = $baseUrl . 'de/standorte-restaurants';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#class="country-de(.+?)</ul#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStoreUrl) {
            $storeUrl = $baseUrl . 'de/' . urlencode($singleStoreUrl);
            if (preg_match('#^http#', $singleStoreUrl)) {
                $storeUrl = $singleStoreUrl;
            }

            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            if (preg_match('#neumuenster#', $storeUrl)) {
                $pattern = '#widgettitle"[^>]*>adresse.+?</b>\s*<br[^>]*>(.+?)</a>#is';
                if (!preg_match($pattern, $page, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                }
                $aAddress = preg_split('#\s*(<br[^>]*>|</p>\s*<p[^>]*>|<a[^>]*>)\s*#', $addressMatch[1]);

                $pattern = '#ffnungszeiten(.+?)</tr#s';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $pattern = '#<td[^>]*>\s*(.+?)\s*</td#';
                    if (preg_match_all($pattern, $storeHoursMatch[1], $storeHoursMatches)) {
                        $eStore->setStoreHours($sTimes->generateMjOpenings(implode(' ', $storeHoursMatches[1])));
                    }
                }

                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[2])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[2])))
                        ->setCity($sAddress->extractAddressPart('city', $aAddress[3]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[3]))
                        ->setPhone($sAddress->normalizePhoneNumber($aAddress[4]))
                        ->setFax($sAddress->normalizePhoneNumber($aAddress[5]))
                        ->setEmail($aAddress[7]);
            } else {

                $pattern = '#address-block[^>]*>\s*<p[^>]*>\s*(.+?)\s*</p>\s*</div#s';
                if (!preg_match($pattern, $page, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                }

                $aAddress = preg_split('#\s*(<br[^>]*>|</p>\s*<p[^>]*>)\s*#', $addressMatch[1]);
                for ($i = 0; $i < count($aAddress); $i++) {
                    if (preg_match('#^DE#', $aAddress[$i])) {
                        $k = 1;
                        if (preg_match('#terminal#i', $aAddress[$i - $k])) {
                            $k++;
                        }
                        $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[$i - $k])))
                                ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[$i - $k])))
                                ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[$i]))
                                ->setCity($sAddress->extractAddressPart('city', $aAddress[$i]))
                                ->setPhone($sAddress->normalizePhoneNumber($aAddress[$i + 1]))
                                ->setFax($sAddress->normalizePhoneNumber($aAddress[$i + 2]));
                        break;
                    }
                }
                $pattern = '#class="description"[^>]*>\s*<p[^>]*>(.+?)</p#s';
                if (preg_match($pattern, $page, $textMatch)) {
                    $eStore->setText($textMatch[1]);
                }

                $pattern = '#feature-list"[^>]*>(.+?)</ul#s';
                if (preg_match($pattern, $page, $serviceListMatch)) {
                    $pattern = '#class="feature[^>]*>\s*(.+?)\s*<#';
                    if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                        $strServices = '';
                        foreach ($serviceMatches[1] as $singleService) {
                            if (preg_match('#rollstuhl#i', $singleService)) {
                                $eStore->setBarrierFree(true);
                                continue;
                            }
                            if (preg_match('#wc#i', $singleService)) {
                                $eStore->setToilet(true);
                                continue;
                            }

                            if (strlen($strServices)) {
                                $strServices .= ', ';
                            }
                            $strServices .= $singleService;
                        }
                    }
                }

                $eStore
                        ->setStoreHours($sTimes->generateMjOpenings(preg_replace('#bis#', '-', $aAddress[count($aAddress) - 1])))
                        ->setService($strServices);
            }
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
