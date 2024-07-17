<?php


/**
 * Store Crawler für Lipo (CH) (ID: 72175)
 */
class Crawler_Company_LipoCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.lipo.ch';
        $searchUrl = $baseUrl . '/de/store-finder/';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $patternStoreList = '#<form[^>]*id="storeFinderDetailsForm"[^>]*action="([^"]+)"#s';
        if (!preg_match_all($patternStoreList, $page, $matchStoreList)) {
            throw new Exception('no stores found on ' . $searchUrl);
        }
        $urlArray = array_unique($matchStoreList[1]);

        foreach ($urlArray as $storeDetailUrl) {
            $storeDetailUrl = str_replace(
                array(' ', '(', ')', 'é', 'ä'),
                array('%20', '%28', '%29', '%C3%A9', '%C3%A4'),
                $storeDetailUrl
            );
            if (preg_match('#Dietikon#i',$storeDetailUrl))
            {
                $storeDetailUrl = str_replace('%20', '%20%20', $storeDetailUrl);
            }


            $sPage->open($baseUrl . $storeDetailUrl);
            $detailPage = $sPage->getPage()->getResponseBody();

            if (!preg_match('#<div[^>]*id="street"[^>]*>\s*(.+?)\s*</div>#', $detailPage, $matchStreet)
                || !preg_match('#<div[^>]*id="town"[^>]*>\s*(.+?)\s*</div>#', $detailPage, $matchCity)
            ) {
                $this->_logger->err('could not match address for store ' . $baseUrl . $storeDetailUrl);
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            if (preg_match('#<div[^>]*id="phone"[^>]*>\s*(.+?)\s*</div>#', $detailPage, $matchPhone)
            ) {
                $eStore->setPhoneNormalized(str_replace('Telefon: ', '', $matchPhone[1]));
            }

            if (preg_match('#<div[^>]*id="fax"[^>]*>\s*(.+?)\s*</div>#', $detailPage, $matchFax)
            ) {
                $eStore->setFaxNormalized(str_replace('Fax: ', '', $matchFax[1]));
            }

            if (preg_match('#<div[^>]*id="openingHours"[^>]*>\s*(.+?)\s*</div>\s*<div[^>]*id="contactDetails"#', $detailPage, $matchOpenings)
            ) {
                $eStore->setStoreHoursNormalized($matchOpenings[1]);
            }

            if (preg_match('#<img[^>]*src="([^"]+)"[^>]*>\s*</div>\s*<div[^>]*id="storeDetailsOverviewContainer"#', $detailPage, $matchImg)) {
                $eStore->setImage($baseUrl . $matchImg[1]);
            }

            $strSpecialDays = '';
            $pattern = '#<div[^>]*class="specialDayOpenings"[^>]*>(.+?)<\/div#s';
            if (preg_match($pattern, $detailPage, $specialOpeningListMatch)) {
                $pattern = '#<ul[^>]*>(.+?)<\/ul#';
                if (preg_match_all($pattern, $specialOpeningListMatch[1], $specialDayListMatches)) {
                    $pattern = '#<small[^>]*>\s*([^<]+?)\s*<\/small#';
                    foreach ($specialDayListMatches[1] as $singleSpecialDay) {
                        if (preg_match_all($pattern, $singleSpecialDay, $infoMatches)) {
                            for ($i = 1; $i < count($infoMatches); $i++) {
                                if (strlen($strSpecialDays)) {
                                    $strSpecialDays .= ', ';
                                }

                                $strSpecialDays .= $infoMatches[$i][0] . ' ' . $infoMatches[$i][1] . ' ' . $infoMatches[$i][2];
                            }
                        }
                    }
                }
            }

            $eStore->setStreetAndStreetNumber($matchStreet[1], 'CH')
                ->setZipcodeAndCity($matchCity[1])
                ->setStoreHoursNotes($strSpecialDays);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}