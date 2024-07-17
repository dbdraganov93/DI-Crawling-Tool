<?php

/*
 * Store Crawler für Denner (CH) (ID: 72116)
 */

class Crawler_Company_DennerCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.denner.ch/';
        $feedUrl = $baseUrl . 'index.php?type=667&tx_dennerstores_storelocator[action]=mapdata&L=0';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage->open($feedUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json as $jsonElement) {
            $aName = preg_split('#\s+#', $jsonElement->name);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($jsonElement->street, 'CH')
                ->setLatitude($jsonElement->lat)
                ->setLongitude($jsonElement->long)
                ->setStoreNumber($jsonElement->uid)
                ->setWebsite($baseUrl . $jsonElement->uri)
                ->setDistribution($aName[1]);

            $sPage->open($eStore->getWebsite());
            $detailPage = $sPage->getPage()->getResponseBody();
            if (preg_match('#<div[^>]*class="[^"]*addressbar\_title[^"]*"[^>]*>(.*?)</div>#', $detailPage, $matchZipCity)) {
                $eStore->setZipcodeAndCity($matchZipCity[1]);
            }

            if (preg_match('#<br[^>]*>\s*Tel[^0-9]*(.+?)\s*<br[^>]*>#', $detailPage, $matchPhone)) {
                $eStore->setPhoneNormalized($matchPhone[1]);
            }

            $strTimes = '';
            if (preg_match('#ffnungszeiten</div>\s*<table[^>]*>\s*(.+?)\s*</table>#', $detailPage, $matchOpenings)) {
                $pattern = '# <td[^>]*itemprop="dayOfWeek"[^>]*>(.+?)<\/tr>#';
                if (preg_match_all($pattern, $matchOpenings[1], $dayMatches)) {
                    $activeDay = '';
                    $strTimes = '';
                    foreach ($dayMatches[1] as $singleLine) {
                        if (preg_match('#(?<day>[^<]*).+?(<time)(?<time>.*)(<\/time)#', $singleLine, $timeMatch)) {
                            $activeDay = strlen(trim($timeMatch['day'])) > 0? trim($timeMatch['day']) : $activeDay;
                            preg_match_all('#(\d\d:\d\d)#', $timeMatch['time'], $time);

                            if (strlen($strTimes)) {
                                $strTimes .= ',';
                            }
                            $strTimes .= substr($activeDay, 0, 2) . ' ' . $time[1][0] . ' - ' . $time[1][1];
                        }
                    }
                }

                $eStore->setStoreHoursNormalized($strTimes);
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

}
