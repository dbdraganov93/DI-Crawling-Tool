<?php

/*
 * Store Crawler für Travel Free (ID: 70960)
 */

class Crawler_Company_TravelFree_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://travel-free.cz/';
        $searchUrl = $baseUrl . 'de.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sDbGeoRegion = new Marktjagd_Database_Service_GeoRegion();
        $cGeoRegion = new Marktjagd_Database_Collection_GeoRegion();

        $aPayments = array(
            'amex' => 'American Express',
            'diners' => 'Diners Club',
            'jcb' => 'JCB Card',
            'maestro' => 'Maestro Card',
            'mastercard' => 'Master Card',
            'visa' => 'Visa Card',
            'vpay' => 'VPay'
        );

        $sDbGeoRegion->fetchAll(TRUE, $cGeoRegion);
        foreach ($cGeoRegion as $singleEntry) {
            $aZipCodes[$singleEntry->getLatitude() . $singleEntry->getLongitude()]['latitude'] = $singleEntry->getLatitude();
            $aZipCodes[$singleEntry->getLatitude() . $singleEntry->getLongitude()]['longitude'] = $singleEntry->getLongitude();
        }

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/(travel-free-shops/[^"]+?)"[^>]*>#s';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();
            $pattern = '#czech\s*=\s*\[([^\]]+?)\]#s';
            if (!preg_match($pattern, $page, $storeGeoMatch)) {
                $this->_logger->err($companyId . ': unable to get store coordinates.');
                continue;
            }
            
            $aGeo = json_decode($storeGeoMatch[1]);
            $storeGeoMatch[1] = $aGeo->lat;
            $storeGeoMatch[2] = $aGeo->lng;
            $distanceToGermanZipCode = 5000;
            
            foreach ($aZipCodes as $zip => $coordinates) {
                $distance = $sAddress->calculateDistanceFromGeoCoordinates(
                        (float) $storeGeoMatch[1], (float) $storeGeoMatch[2], (float) $coordinates['latitude'], (float) $coordinates['longitude']
                );

                if ($distance < $distanceToGermanZipCode) {
                    $distanceToGermanZipCode = $distance;
                    $aCoordinatesToUse['latitude'] = $coordinates['latitude'];
                    $aCoordinatesToUse['longitude'] = $coordinates['longitude'];
                }
            }
            
            if ($distanceToGermanZipCode > 10) {
                continue;
            }
            
            $pattern = '#adresse\s*</h2>\s*<p[^>]*>\s*(.+?)\s*</p>#is';
            if (!preg_match($pattern, $page, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $aAddress = preg_split('#\s*<[^>]*>\s*#', $storeAddressMatch[1]);
            
            $pattern = '#ffnungszeiten(.+?)</tbody#s';
            if (!preg_match($pattern, $page, $storeHoursMatch)) {
                $this->_logger->err($companyId . ': unable to get store hours: ' . $storeDetailUrl);
            }

            $pattern = '#ul[^>]*class="zahlungsmittel"[^>]*>(.+?)</ul#i';
            if (preg_match($pattern, $page, $storePaymentListMatch)) {
                $pattern = '#alt="([^"]+?)"#';
                if (preg_match_all($pattern, $storePaymentListMatch[1], $storePaymentMatches)) {
                    $strPayment = '';
                    foreach ($storePaymentMatches[1] as $singlePayment) {
                        if (strlen($strPayment)) {
                            $strPayment .= ', ';
                        }

                        $strPayment .= preg_replace('#^cash#', 'bar', $singlePayment);
                    }
                }
            }

            $pattern = '#dienstleistungen(.+?)</div#is';
            $strSection = '';
            if (preg_match($pattern, $page, $sectionListMatch)) {
                $patternSimple = '#<strong[^>]*>([^<]+?)\s*:?\s*</strong>\s*</p>\s*(<p[^>]*>.+?</p>)#s';
                $patternCrazy = '#<p[^>]*><strong[^>]*>(.+?)</strong>(.+?)<img#';
                if (preg_match_all($patternSimple, $sectionListMatch[1], $sectionMatches)) {
                    for ($i = 0; $i < count($sectionMatches[1]); $i++) {
                        if (strlen($strSection)) {
                            $strSection .= ', ';
                        }
                        $strSection .= $sectionMatches[1][$i];
                        if (preg_match('#>([^<]+?)<[^>]*>(\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2})\s*<#', $sectionMatches[2][$i], $timeMatch)) {
                            $strSection .= ' ' . $timeMatch[1] . ' ' . $timeMatch[2];
                        } elseif (preg_match('#nonstop#', $sectionMatches[2][$i])) {
                            $strSection .= ' Montag - Sonntag 00:00 - 24:00';
                        }
                    }
                }
                elseif (preg_match($patternCrazy, $sectionListMatch[1], $sectionMatch)) {
                    $strSection = strip_tags($sectionMatch[1]);
                    $pattern = '#<p[^>]*>([^<]+?)\s*:?\s*<br[^>]*>(\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2})\s*</p>#';
                    if (preg_match_all($pattern, $sectionMatch[2], $timesMatches)) {
                        for ($j = 0; $j < count ($timesMatches[1]); $j++) {
                            if (!preg_match('#' . strip_tags($sectionMatch[1]) . '$#', $strSection)) {
                                $strSection .= ', ';
                            }
                            $strSection .= $timesMatches[1][$j] . ' ' . $timesMatches[2][$j];
                        }
                    }
                }
            }
            
            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $eStore->setLatitude($aCoordinatesToUse['latitude'])
                    ->setLongitude($aCoordinatesToUse['longitude'])
                    ->setWebsite($storeDetailUrl)
                    ->setStreet($sAddress->extractAddressPart('street', $aAddress[1]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[1]))
                    ->setZipcodeAndCity(preg_replace(array('#,#','#(\d+)\s+(\d+)#'), array('', '$1$2'), $aAddress[2]))
                    ->setStoreHoursNormalized($storeHoursMatch[1])
                    ->setPayment($strPayment)
                    ->setSection($strSection);

            if ($eStore->getStoreNumber() == 's20') {
                $eStore->setCity('Stožec')
                        ->setZipcode('38444')
                        ->setStreet('Hraniční přechod Nové Údolí');
            }

            if ($eStore->getStoreNumber() == 's26') {
                $eStore->setCity('Železná')
                        ->setZipcode('34525')
                        ->setStreet('Bělá nad Radbuzou');
            }
            
            if ($eStore->getStoreNumber() == 's7') {
                $eStore->setStoreHoursNormalized('Mo - So 07:00 - 21:00');
            }
            
            $strOffers = '';
            $pattern = '#<ul[^>]*class="sortiment"[^>]*>(.+?)</ul#s';
            if (preg_match($pattern, $page, $offerListMatch)) {
                $pattern = '#<a[^>]*>\s*([^<]+?)\s*<#s';
                if (preg_match_all($pattern, $offerListMatch[1], $offerMatches)) {
                    foreach ($offerMatches[1] as $singleOffer) {
                        if (strlen($strOffers)) {
                            $strOffers .= ', ';
                        }
                        $strOffers .= preg_replace('#Parfum#', 'Parfüm', $singleOffer);
                    }
                    $eStore->setText('Im Travel FREE Shop in ' . $aAddress[0] . ' findest du original Markenware von internationalen und tschechischen Herstellern aus den Bereichen:  ' . $strOffers . '.');
                }
            }
            
            $strServices = '';
            $pattern = '#<ul[^>]*class="services"[^>]*>(.+?)</ul#s';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<li[^>]*>\s*([^<]+?)\s*<#s';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    foreach ($serviceMatches[1] as $singleService) {
                        if (strlen($strServices)) {
                            $strServices .= '. ';
                        }
                        $strServices .= $singleService;
                    }
                    $eStore->setService($strServices . '.');
                }
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
