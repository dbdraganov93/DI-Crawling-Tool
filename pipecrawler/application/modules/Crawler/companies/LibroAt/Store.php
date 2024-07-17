<?php

/**
 * Store Crawler für Libro AT (ID: 73271)
 */

class Crawler_Company_LibroAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.libro.at/';
        $searchUrl = $baseUrl . 'ustorelocator/location/search/?lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '&lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page(TRUE);
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.4);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            sleep(1);
            $this->_logger->info($companyId . ': opening page ' . $singleUrl);
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $xmlStores = simplexml_load_string($page);

            foreach ($xmlStores->marker as $singleStore) {
                if (!preg_match('#AT#', $singleStore->attributes()->country_id)) {
                    $this->_logger->info($companyId . ': not an austrian store.');
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber((string)$singleStore->attributes()->store_id)
                    ->setAddress($singleStore->attributes()->address_display, $singleStore->attributes()->city_address, 'AT')
                    ->setLatitude((string)$singleStore->attributes()->latitude)
                    ->setLongitude((string)$singleStore->attributes()->longitude)
                    ->setDistribution((string)$singleStore->attributes()->region)
                    ->setStoreHoursNormalized(strip_tags(preg_replace('#\s*</li>\s*<li[^>]*>#', ',', (string)$singleStore->attributes()->notes)))
                    ->setWebsite((string)$singleStore->attributes()->website_url)
                    ->setFaxNormalized((string)$singleStore->attributes()->fax)
                    ->setPhoneNormalized((string)$singleStore->attributes()->phone);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }
}
