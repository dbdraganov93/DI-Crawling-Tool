<?php

/**
 * Store Crawler für MercedesBenz (ID: 68768)
 */
class Crawler_Company_MercedesBenz_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://dealerlocator.mercedes-benz.com/';
        $searchUrl = $baseUrl . 'dl/api/v1/DLp/outlet-de-wholesale/de_DE/dealer_by_citypostcode'
                . '?token=dl-responsive&config=DLp'
                . '&postcode=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&subregion=&region=&includeDealerData=true&includeDealerPrograms=false';
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sAddress = new Marktjagd_Service_Text_Address();
        $sGen = new Marktjagd_Service_Generator_Url();

        $cStores = new Marktjagd_Collection_Api_Store();

        $page = $sPage->getPage();
        $client = $page->getClient();

        $client->setHeaders('X-Requested-With', 'XMLHttpRequest');
        $client->setHeaders('Referer', 'http://dealersearch.mercedes-benz.com/is-bin/intershop.static/WFS/outlet-de-wholesale-consumer-Site/-/de_DE/widget/dealerselector/theme/eMB-MyM-DL/index.html?sku=DLp');

        $page->setClient($client);
        $sPage->setPage($page);

        $aUrls = $sGen->generateUrl($searchUrl, 'zip', 5);

        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores->dealers as $dealer) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setSubtitle($dealer->formattedData->nameline1 . ", " . $dealer->formattedData->nameline2)
                        ->setWebsite($dealer->formattedData->links[0])
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $dealer->formattedData->cityZipcodeLine))
                        ->setCity($sAddress->extractAddressPart('city', $dealer->formattedData->cityZipcodeLine))
                        ->setStreet($sAddress->extractAddressPart('street', $dealer->formattedData->addressLine1))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $dealer->formattedData->addressLine1))
                        ->setFax($sAddress->normalizePhoneNumber($dealer->formattedData->faxLine))
                        ->setPhone($sAddress->normalizePhoneNumber($dealer->formattedData->phoneLine))
                        ->setEmail($dealer->formattedData->email)
                        ->setLatitude((string) $dealer->geoRepresentation->gpsY)
                        ->setLongitude((string) $dealer->geoRepresentation->gpsX)
                        ->setStoreNumber($eStore->getLatitude() . '/' . $eStore->getLongitude());

                $cStores->addElement($eStore, TRUE);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
