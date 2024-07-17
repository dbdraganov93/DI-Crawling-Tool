<?php
/**
 * Store Crawler für Screwfix (ID: 72086)
 */

class Crawler_Company_Screwfix_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.screwfix.de/';
        $searchUrl = $baseUrl . 'baumarkt';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#var\s*descriptions\s*=\s*([^;]+?);#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $jStores = json_decode($storeListMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $jStoreInfos = $singleJStore->description;
            if (!preg_match('#Deutschland#', $jStoreInfos->country)) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setZipcode($jStoreInfos->postal_code)
                ->setStreetAndStreetNumber($jStoreInfos->address)
                ->setStoreNumber($jStoreInfos->shop_id)
                ->setCity(preg_replace('#Baumarkt\s*#', '', $jStoreInfos->shop_name))
                ->setLatitude($jStoreInfos->latitude)
                ->setLongitude($jStoreInfos->longitude);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}