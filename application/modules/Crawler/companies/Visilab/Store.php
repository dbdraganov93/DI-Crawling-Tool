<?php

/*
 * Store Crawler für Visilab (CH) (ID: 72239)
 */

class Crawler_Company_Visilab_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.visilab.ch/';
        $searchUrl = $baseUrl . 'de/optiker-visilab/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*id="store\_[^"]*"[^>]*onclick="\s*[^\(]*\(([^\,]+)\s*\,\s*([^,]+)\s*\,'
            . '.*?<h3>([^<]+)</h3>\s*'
            . '<p>(.+?)</p>\s*'
            . '<div>\s*<a[^>]*href="([^"]+)"'
            . '#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[0] as $key => $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setLatitude($storeMatches[1][$key]);
            $eStore->setLongitude($storeMatches[2][$key]);

            $aAddress = explode('<br>', $storeMatches[4][$key]);

            if (count($aAddress) == 2) {
                $eStore->setStreetAndStreetNumber($aAddress[0]);
                $eStore->setZipcodeAndCity($aAddress[1]);
            } else if (count($aAddress) == 3) {
                $eStore->setSubtitle($aAddress[0]);
                $eStore->setStreetAndStreetNumber($aAddress[1]);
                $eStore->setZipcodeAndCity($aAddress[2]);
            } else if (count($aAddress) == 4) {
                $eStore->setSubtitle($aAddress[0]);
                $eStore->setStreetAndStreetNumber($aAddress[1]);
                $eStore->setZipcodeAndCity($aAddress[3]);
            }

            $eStore->setWebsite($storeMatches[5][$key]);

            $sPage->open($eStore->getWebsite());
            $detailPage = $sPage->getPage()->getResponseBody();

            $patternPhone = '#<meta[^>]*itemprop="telephone"[^>]*content="([^"]+)"#is';
            if (preg_match($patternPhone, $detailPage, $matchPhone)) {
                $eStore->setPhoneNormalized($matchPhone[1]);
            }

            $patternFax = '#<meta[^>]*itemprop="faxNumber"[^>]*content="([^"]+)"#is';
            if (preg_match($patternFax, $detailPage, $matchFax)) {
                $eStore->setFaxNormalized($matchFax[1]);
            }

            $patternMail = '#<meta[^>]*itemprop="email"[^>]*content="([^"]+)"#is';
            if (preg_match($patternMail, $detailPage, $matchMail)) {
                $eStore->setEmail($matchMail[1]);
            }

            $patternOpening = '#<div[^>]*class="block\-title"[^>]*>[^<]*ffnungszeiten[^<]*</div>'
                .'\s*<div[^>]*>.*?'
                . '<tr>\s*<td[^>]*>\s*(.*?)\s*</td>\s*<td[^>]*>\s*(.*?)\s*</td>'
                . '#';

            if (preg_match($patternOpening, $detailPage, $matchOpening)) {
                $aWeekdays = explode('<br/>', $matchOpening[1]);
                $aTimes = explode('<br/>', $matchOpening[2]);
                $aOpenings = array_combine($aWeekdays, $aTimes);

                $sTimes = '';
                foreach ($aOpenings as $weekday => $time) {
                    if (strlen($sTimes) > 0) {
                        $sTimes .= ', ';
                    }

                    $sTimes .= $weekday . ' ' . $time;
                }

                $eStore->setStoreHoursNormalized($sTimes);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
