<?php

/**
 * Storecrawler für L´Occitane (ID: 28948)
 */
class Crawler_Company_LOccitane_Store extends Crawler_Generic_Company
{

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $storeUrl = 'http://de.loccitane.com/tools/datafeeds/StoresJSON.aspx?country=germany&city=&lat=&lon=';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($storeUrl);
        $jsonString = $sPage->getPage()->getResponseBody();
        $json = json_decode($jsonString);

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($json->storeList->store as $store) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setZipcode(preg_replace('#[^0-9]#', '', $store->ZipCode))
                   ->setStoreNumber($store->storeID)
                   ->setPhone($sAddress->normalizePhoneNumber($store->Phone))
                   ->setStreet($sAddress->normalizeStreet(
                        $sAddress->extractAddressPart('street', $store->Address1)))
                   ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $store->Address1))
                   ->setStoreHours($sTimes->generateMjOpenings($store->OpeningHours1 . ' ' . $store->OpeningHours2));

            if ($eStore->getStreet() == 'Po Box'
                || $eStore->getStreet() == 'Postfach'
            ) {
                continue;
            }

            $city = $sAddress->normalizeCity($store->City);
            $city = preg_replace('#Dresde$#', 'Dresden', $city);
            $eStore->setCity($city);

            if (strlen(trim($store->Address2))) {
                $eStore->setSubtitle($store->Address2);
            }

            if (strlen(trim($store->Address3))) {
                $subtitle = $eStore->getSubtitle();
                if (strlen($subtitle)) {
                    $subtitle .= ' / ';
                }

                $subtitle .= $store->Address3;
                $eStore->setSubtitle($subtitle);
            }

            if (!strlen(trim($eStore->getZipcode()))
                || !strlen(trim($eStore->getStreet()))
            ) {
                continue;
            }

            $cStores->addElement($eStore, true);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
