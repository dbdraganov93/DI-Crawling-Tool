<?php

/**
 * Storecrawler für Hollister (ID: 68012)
 *
 * Class Crawler_Company_Hollister_Store
 */
class Crawler_Company_Hollister_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $sUrl = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'https://de-eu.hollisterco.com';
        $storeFinderUrl = $baseUrl . '/api/ecomm/h-eu/storelocator/search/?latitude=' . $sUrl::$_PLACEHOLDER_LAT
            . '&longitude=' . $sUrl::$_PLACEHOLDER_LON
            . '&radius=50&radiusUOM=SMI&brand=HOL';

        $aUrls = $sUrl->generateUrl($storeFinderUrl, $sUrl::$_TYPE_COORDS, 0.5);

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $cStore = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $url) {
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();

            $jsonStores  = json_decode($page);
            foreach ($jsonStores->physicalStores as $jsonStore) {
                if ((string) $jsonStore->country != 'DE') {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setSubtitle((string) $jsonStore->name)
                    ->setStreetAndStreetNumber((string) $jsonStore->addressLine[0])
                    ->setCity((string) $jsonStore->city)
                    ->setZipcode((string) $jsonStore->postalCode)
                    ->setPhone($sAddress->normalizePhoneNumber((string) $jsonStore->telephone))
                    ->setLatitude((string) $jsonStore->latitude)
                    ->setLongitude((string) $jsonStore->longitude);

                foreach ($jsonStore->physicalStoreAttribute as $attribute) {
                    if ((string) $attribute->name == 'hours-Week1') {
                        $aTimes = explode(',', str_replace('|', '-', (string) $attribute->value));
                        $aDays = array('Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So');
                        $aOpenings = array_combine($aDays, $aTimes);

                        $sOpenings = '';
                        foreach ($aOpenings as $day => $time) {
                            if ($time == '00:00-00:00') {
                                continue;
                            }

                            if (strlen($sOpenings)) {
                                $sOpenings .= ', ';
                            }

                            $sOpenings .= $day . ' ' . $time;
                        }

                        $eStore->setStoreHoursNormalized($sOpenings);
                    }

                    if ((string) $attribute->name == 'StoreNumber') {
                        $eStore->setStoreNumber((string) $attribute->value);
                    }
                }

                $cStore->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId, false);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}