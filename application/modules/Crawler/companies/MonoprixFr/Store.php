<?php

/*
 * Store Crawler für Monoprix FR (ID: 72324)
 */

class Crawler_Company_MonoprixFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.monoprix.fr/';
        $searchUrl = $baseUrl . 'trouver-nos-magasins';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#allStores\.push\((.+?)\);#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $jStore = json_decode($singleStore);
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($jStore->address1)
                ->setCity(ucwords(strtolower($jStore->city)))
                ->setStoreNumber($jStore->id)
                ->setZipcode($jStore->postalCode)
                ->setLongitude($jStore->longitude)
                ->setLatitude($jStore->latitude);

            if (strlen($eStore->getStoreNumber())) {
                $eStore->setWebsite($baseUrl . 'jsp/monoprixandme/magasin/store.jsp?storeId=' . $eStore->getStoreNumber());

                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<div[^>]*class="horaires-store"[^>]*>(.+?)</table#s';
                if (preg_match($pattern, $page, $storeHoursListMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursListMatch[1], 'text', TRUE, 'fra');
                }
            }

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
