<?php
/**
 * Store Crawler für Pagro AT (ID: 72441)
 */

class Crawler_Company_PagroAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.pagro.at/';
        $searchUrl = $baseUrl . 'ustorelocator/location/search/?lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '&lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page(TRUE);
        $sGen = new Marktjagd_Service_Generator_Url();

        $this->_logger->info($companyId . ': generating urls.');
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.1, 'AT');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $this->_logger->info($companyId . ': opening ' . $singleUrl);
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $xmlStores = simplexml_load_string($page);

            foreach ($xmlStores->marker as $singleXmlStore) {
                if (!preg_match('#AT#', strval($singleXmlStore->attributes()->country_id))
                    || !strval($singleXmlStore->attributes()->is_active)) {
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber(strval($singleXmlStore->attributes()->store_id))
                    ->setLatitude(strval($singleXmlStore->attributes()->latitude))
                    ->setLongitude(strval($singleXmlStore->attributes()->longitude))
                    ->setDistribution(strval($singleXmlStore->attributes()->region))
                    ->setZipcodeAndCity(strval($singleXmlStore->attributes()->city_address), 'AT')
                    ->setStreetAndStreetNumber(strval($singleXmlStore->attributes()->street))
                    ->setStoreHoursNormalized(strval($singleXmlStore->attributes()->notes))
                    ->setPhoneNormalized(strval($singleXmlStore->attributes()->phone));

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }
}