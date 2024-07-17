<?php
/**
 * Store Crawler für Billa AT (ID: 73282)
 */

class Crawler_Company_BillaAt_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $aSubBrands = [
            '73282' => '#^(Billa)$#',
            '72681' => '#^(Billa\s*Corso)$#',
            '73375' => '#^(Billa\s*Plus)$#'
        ];
        $baseUrl = 'https://www.billa.at/';
        $searchUrl = $baseUrl . 'api/stores';
        $jStores = $this->getJStores($searchUrl);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match($aSubBrands[$companyId], $singleJStore->subBrand)) {
                continue;
            }
            $eStore = $this->createStore($singleJStore);

            $cStores->addElement($eStore);
        }


        return $this->getResponse($cStores);
    }

    /**
     * @param string $searchUrl
     * @return array
     * @throws Exception
     */
    public function getJStores(string $searchUrl): array
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);

        return $sPage->getPage()->getResponseAsJson();
    }

    /**
     * @param $singleJStore
     * @return Marktjagd_Entity_Api_Store
     */
    public function createStore($singleJStore): Marktjagd_Entity_Api_Store
    {
        $eStore = new Marktjagd_Entity_Api_Store();

        $eStore->setStoreNumber($singleJStore->storeId)
            ->setPhoneNormalized($singleJStore->phone)
            ->setCity($singleJStore->city)
            ->setLongitude($singleJStore->coordinate->x)
            ->setLatitude($singleJStore->coordinate->y)
            ->setDistribution($singleJStore->providence)
            ->setStreetAndStreetNumber($singleJStore->street)
            ->setZipcode($singleJStore->zip)
            ->setSection(implode(', ', $singleJStore->features))
            ->setParking($singleJStore->parking->cost)
            ->setStoreHoursNormalized($singleJStore->openingTimesExplanation);

        return $eStore;
    }

}
