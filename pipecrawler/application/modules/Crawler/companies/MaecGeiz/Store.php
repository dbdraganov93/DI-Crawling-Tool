<?php

/**
 * Storecrawler für Mäc Geiz (ID: 351)
 */
class Crawler_Company_MaecGeiz_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        /**
         * For ticket 74839, PDM asked for 4 state-based
         * distributions
         */
        $distributions = array(
            "Berlin",
            "Brandenburg",
            "Hamburg",
            "Hessen"
        );

        $baseUrl = 'https://www.mac-geiz.de/';
        $searchUrl = $baseUrl . 'ustorelocator/location/search/?'
            . 'lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '&lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $page = $sPage->getPage();
        $page->setAlwaysHtmlDecode(false)
            ->setAlwaysStripComments(false)
            ->setUseCookies(true);
        $sPage->setPage($page);

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 1);

        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $xml = simplexml_load_string($page);
            $city = array();

            foreach ($xml->marker as $marker) {
                if (!preg_match('#DE#', $marker->attributes()->country_id)) {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber((string)$marker->attributes()->store_id)
                    ->setLatitude((string)$marker->attributes()->latitude)
                    ->setLongitude((string)$marker->attributes()->longitude)
                    ->setStreetAndStreetNumber((string)$marker->attributes()->street)
                    ->setZipcode(preg_replace('#[^\d]+#', '', trim((string)$marker->attributes()->postcode)))
                    ->setCity(trim((string)$marker->attributes()->city))
                    ->setPhoneNormalized((string)$marker->attributes()->phone)
                    ->setFaxNormalized((string)$marker->attributes()->fax)
                    ->setWebsite((string)$marker->attributes()->website_url)
                    ->setStoreHoursNormalized((string)$marker->attributes()->notes);

                $state = $sDbGeo->findRegionByZipCode($eStore->getZipcode());
                if (in_array($state, $distributions)) {
                    $eStore->setDistribution($state);
                }

                array_push($city, $eStore->getCity());

                $cStores->addElement($eStore);
            }
            // Store Lauchhammer
            if (!in_array('Lauchhammer', $city)) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber('id:1533567')
                    ->setStreetAndStreetNumber('Wilhelm-Pieck-Straße 33-35')
                    ->setZipcodeAndCity('01979 Lauchhammer')
                    ->setLatitude('51.4468841')
                    ->setLongitude('13.56960')
                    ->setStoreHoursNormalized('Mo - Fr: 09:00 - 19:00, Sa: 09:00 - 16:00');

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }
}

