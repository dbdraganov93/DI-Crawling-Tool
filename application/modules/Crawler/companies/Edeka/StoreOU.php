<?php

/**
 * Store crawler for EDEKA Offers Unlimited (ID: 89954)
 */

class Crawler_Company_Edeka_StoreOU extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $cStores = new Marktjagd_Collection_Api_Store();

        $cStores->addElements($sApi->findStoresByCompany('72089')->getElements());
        $cStores->addElements($sApi->findStoresByCompany('72090')->getElements());
        $cStores->addElements($sApi->findStoresByCompany('72301')->getElements());


        return $this->getResponse($cStores);
    }
}