<?php

/*
 * Store Crawler für Bijou Brigitte (ID: 28997)
 */

class Crawler_Company_BijouBrigitte_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://ssl.bijou-brigitte.com/';
        $searchUrl = $baseUrl . 'filialfinder.php';
        $sPage = new Marktjagd_Service_Input_Page();
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $cStores = new Marktjagd_Collection_Api_Store();
        $sGeo = new Marktjagd_Database_Service_GeoRegion();
        $aZip = $sGeo->findAllZipCodes();

        foreach ($aZip as $zipcode) {
            $postVars = array(
                'do_action'	=> 'do_search',
                'suchwort'	=> $zipcode,
            );

            $sPage->open($searchUrl, $postVars);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<tr[^>]*>\s*' .
                '<td[^>]*>[^<]*</td>\s*' .
                '<td[^>]*>\s*([0-9]{5})\s+([^,]+),([^,]+,)?([^<]+)<br[^>]*>\s*' .
                '<a[^>]*>Karte anzeigen</a>\s*</td>\s*' .
                '</tr>#';
            if (!preg_match_all($pattern, $page, $sMatches)) {
                continue;
            }

            foreach ($sMatches as $key => $value) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setZipcode($sMatches[1][$key]);
                $eStore->setCity(trim($sMatches[2][$key]));
                $eStore->setStreetAndStreetNumber(trim($sMatches[4][$key]));

                if ($eStore->getZipcode() == '94032'
                    && strtolower($eStore->getStreet()) == 'wittgasse'){
                    $eStore->setStoreHoursNormalized('Mo-Sa 09:30-20:00');
                }

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
