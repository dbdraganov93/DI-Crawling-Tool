<?php

/*
 * Store Crawler für Crumpler (ID: 71944)
 */

class Crawler_Company_Crumpler_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.crumpler.eu';
        $searchUrl = $baseUrl . '/storelocator/index/loadstore/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match_all('#<li\s*id="s_store[^"]*"[^>]*>(.*?)\s*<div[^>]*class=\'store_navigation\'[^>]*>\s*</div>\s*</li>#is', $page, $matchStores)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($matchStores[1] as $sStores) {
            $patternAddress = '#<h5[^>]*>(.*?)</h5>\s*<p>(.*?)<br>(.*?)<br>(.*?)</p>\s*<p>(.*?)</p>#is';
            if (preg_match($patternAddress, $sStores, $matchAddress)) {
                if (!preg_match('#crumpler#is', $matchAddress[1]) || $matchAddress[4] != 'DE') {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStreetAndStreetNumber($matchAddress[2]);
                $eStore->setZipcodeAndCity($matchAddress[3]);

                if ($eStore->getCity() == 'Cologne') {
                    $eStore->setCity('Köln');
                }

                if (preg_match('#"mailto:([^"]+)"#', $sStores, $matchMail)) {
                    $eStore->setEmail($matchMail[1]);
                }

                if (preg_match('#<div[^>]*id="openingHours[^"]*"[^>]*>(.*?)</div>#', $sStores, $matchOpening)) {
                    $aPattern = array(
                        '#\s*\bmo[a-z]*\s*-\s*#i',
                        '#\s*\bdi[a-z]*\s*-\s*#i',
                        '#\s*\btu[a-z]*\s*-\s*#i',
                        '#\s*\bmi[a-z]*\s*-\s*#i',
                        '#\s*\bwe[a-z]*\s*-\s*#i',
                        '#\s*\bdo[a-z]*\s*-\s*#i',
                        '#\s*\bth[a-z]*\s*-\s*#i',
                        '#\s*\bfr[a-z]*\s*-\s*#i',
                        '#\s*\bsa[a-z]*\s*-\s*#i',
                        '#\s*\bso[a-z]*\s*-\s*#i',
                        '#\s*\bsu[a-z]*\s*-\s*#i',
                    );

                    $aReplacement = array(
                        'Mo ',
                        'Di ',
                        'Di ',
                        'Mi ',
                        'Mi ',
                        'Do ',
                        'Do ',
                        'Fr ',
                        'Sa ',
                        'So ',
                        'So ',
                    );

                    $openings = preg_replace($aPattern, $aReplacement, $matchOpening[1]);
                    $eStore->setStoreHoursNormalized($openings);
                }

                if (preg_match('#calcRoute\([^,]*,([^,]*),([^,]*),[^,]*\)#', $sStores, $matchGeo)) {
                    $eStore->setLatitude($matchGeo[1]);
                    $eStore->setLongitude($matchGeo[2]);
                }

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
