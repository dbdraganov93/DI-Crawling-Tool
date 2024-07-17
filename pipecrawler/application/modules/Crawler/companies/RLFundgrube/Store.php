<?php

/*
 * Store Crawler für RL Fundgrube (ID: 69972)
 */

class Crawler_Company_RLFundgrube_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.rl-fundgrube.de/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#const\s*locations\s*=\s*\{([^\}]+?)\};#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#(\d+:\s*\[\[.+?\]\],)#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            if (preg_match('#Zentrale#', $singleStore)) {
                continue;
            }

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address - ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#^(\d+):#';
            if (preg_match($pattern, $singleStore, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }

            $pattern = '#<br[^>]*>\s*([\d\/\s^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#<ul[^>]*>(.+?)<\/ul#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#\',\s*([\d\.]+?),\s*([\d\.]+?),#';
            if (preg_match($pattern, $singleStore, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                    ->setLongitude($geoMatch[2]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2]);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
