<?php

/**
 * Store Crawler für Netto Markendiscount (ID: 103)
 */
class Crawler_Company_NettoMarkendiscount_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $oPage->setUseCookies(true);
        $sPage->setPage($oPage);

        $baseUrl = 'https://www.netto-online.de/';
        $searchUrl = $baseUrl . 'api/stores/get_all';
        $aParams = [
            'api_user' => 'Offerista',
            'api_token' => 'HXWCrNdZtUo00IscGVrg',
        ];

        $sPage->open($searchUrl, $aParams);
        $json = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($json->data as $store) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($store->store_id)
                ->setStreetAndStreetNumber($store->street)
                ->setZipcode($store->post_code)
                ->setCity($store->city)
                ->setStoreHoursNormalized($store->store_opening)
                ->setLatitude($store->coord_latitude)
                ->setLongitude($store->coord_longitude)
                ->setWebsite($store->link_flipbook)
                ->setDistribution($store->region_nl_shorthandle);

            if ($store->is_city == 1) {
                $eStore->setTitle('Netto City');
            }elseif ($store->store_type_id == 4) {
                $eStore->setTitle('Netto Getränke-Discount');
            }

            $cStores->addElement($eStore, TRUE);

        }

        return $this->getResponse($cStores);
    }
}