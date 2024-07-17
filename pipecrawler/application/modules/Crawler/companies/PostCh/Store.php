<?php

/* 
 * Store Crawler für Post CH (ID: 72187)
 */

class Crawler_Company_PostCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://places.post.ch/';
        $searchUrl = $baseUrl . 'StandortSuche/StaoCacheService/Find?query=T9&clusterdist=1&lang=de&' .
            'extent=5.363694265484808%2C45.29410962103148%2C10.740662649273872%2C48.549628397979205&' .
            'autoexpand=false&maxpois=50000&agglevel=1&encoding=UTF-8&_=1594201543704';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson()->pois;

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStorePack) {
            if (!property_exists($singleJStorePack, 'pois')) {
                continue;
            }

            foreach ($singleJStorePack->pois as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->id)
                    ->setLongitude($singleJStore->x)
                    ->setLatitude($singleJStore->y)
                    ->setStreetAndStreetNumber($singleJStore->info->Street, 'CH')
                    ->setZipcode($singleJStore->info->Zip)
                    ->setCity($singleJStore->info->City);

                $cStores->addElement($eStore, TRUE);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }
}