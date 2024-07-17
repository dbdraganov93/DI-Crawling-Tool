<?php

/**
 * Store Crawler für Spar Express (ID: 67456)
 */
class Crawler_Company_SparExpress_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://api.shopsuche.spar-express.de/';
        $searchUrl = $baseUrl . 'sparexpressstores?page=0&size=500';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jsonData = $sPage->getPage()->getResponseAsJson();
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($jsonData->entries as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($singleJStore->street)
                ->setZipcode($singleJStore->zipCode)
                ->setCity($singleJStore->city)
                ->setText($singleJStore->description)
                ->setPhoneNormalized($singleJStore->phone)
                ->setStoreHoursNormalized(preg_replace('#(\w);(\d)#', '$1 $2', $singleJStore->openingHours));

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}
