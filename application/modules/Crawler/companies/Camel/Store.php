<?php

/**
 * Storecrawler für Camel Active (ID: 28949)
 */
class Crawler_Company_Camel_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $sGenerator = new Marktjagd_Service_Generator_Url();
        $servicePage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $cStore = new Marktjagd_Collection_Api_Store();

        $storeListUrl = 'http://www.camelactive.de/app/stores/data/collection//bounds/'
                        . $sGenerator::$_PLACEHOLDER_LAT . ','
                        . $sGenerator::$_PLACEHOLDER_LON . ','
                        . $sGenerator::$_PLACEHOLDER_LAT_STEP . ','
                        . $sGenerator::$_PLACEHOLDER_LON_STEP;

        $aUrls = $sGenerator->generateUrl($storeListUrl, $sGenerator::$_TYPE_RECT, 0.5);



        foreach ($aUrls as $url) {
            $servicePage->open($url);
            $page = $servicePage->getPage()->getResponseBody();
            $aStores = json_decode($page);

            foreach ($aStores as $store) {
                if ($store->land != 'DE'
                    || !preg_match('#camel\s*active\s*Store#is', $store->name)
                ) {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($store->id)
                       ->setLatitude($store->lat)
                       ->setLongitude($store->lng)
                       ->setTitle($store->name)
                       ->setStreet($sAddress->extractAddressPart('street', $store->adresse))
                       ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $store->adresse))
                       ->setZipcode($store->plz)
                       ->setCity($store->ort)
                       ->setPhone($sAddress->normalizePhoneNumber($store->telefon))
                       ->setFax($sAddress->normalizePhoneNumber($store->telefax))
                       ->setWebsite($store->homepage);

                $cStore->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}