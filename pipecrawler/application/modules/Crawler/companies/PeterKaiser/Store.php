<?php

/*
 * Store Crawler für Peter Kaiser (ID: 72084)
 */

class Crawler_Company_PeterKaiser_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sUrl = new Marktjagd_Service_Generator_Url();
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $baseUrl = 'https://www.peter-kaiser.de/';
        $searchUrl = $baseUrl . 'geocode_bk.php?lat='
            . $sUrl::$_PLACEHOLDER_LAT . '&lon='
            . $sUrl::$_PLACEHOLDER_LON . '&country=DE';

        $aUrl = $sUrl->generateUrl($searchUrl, 'coords', '0.2');

        foreach ($aUrl as $url) {
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();
            $xml = simplexml_load_string($page);
            libxml_use_internal_errors(true);
            if (!$xml) {
                continue;
            }

            foreach($xml->marker as $marker) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStreetAndStreetNumber(ucwords((string) $marker['address']));
                $eStore->setZipcodeAndCity((string) $marker['city']);
                $eStore->setCity(ucwords($eStore->getCity()));
                $eStore->setLatitude((string) $marker['lat']);
                $eStore->setLongitude((string) $marker['lng']);
                $eStore->setStoreNumber((string) $marker['id']);
                $cStores->addElement($eStore, true);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
