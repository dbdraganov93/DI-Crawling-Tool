<?php

/* 
 * Store Crawler für Pfister (CH) (ID: 72143)
 */

class Crawler_Company_PfisterCh_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.pfister.ch/';
        $searchUrl = $baseUrl . 'de/filialen';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<script[^>]*id="__NEXT_DATA__"[^>]*type="application/json"[^>]*>(.+?)<\/script#';
        if (!preg_match($pattern, $page, $infoListMatch)) {
            throw new Exception($companyId . ': unable to get info json.');
        }
        $jInfos = json_decode($infoListMatch[1]);
        $jStores = $jInfos->props->initialProps->pageProps->cmsData->content[1]->contentSlot->components[0]->data->data->locations;

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($singleJStore->address)
                ->setWebsite($baseUrl . 'de/' . trim($singleJStore->branchLabel, '/'))
                ->setCity($singleJStore->city)
                ->setStoreNumber($singleJStore->code)
                ->setEmail($singleJStore->email)
                ->setLatitude($singleJStore->latitude)
                ->setLongitude($singleJStore->longitude)
                ->setPhoneNormalized($singleJStore->phone)
                ->setZipcode($singleJStore->zip);

            if (strlen($eStore->getWebsite())) {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<table[^>]*class="zeit"[^>]*>(.+?)<\/tbody>#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}
