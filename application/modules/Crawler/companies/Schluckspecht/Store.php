<?php

/*
 * Store Crawler für Schluckspecht (ID: 69543)
 */

class Crawler_Company_Schluckspecht_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.schluckspecht.com/';
        $searchUrl = $baseUrl . 'filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();

        $aZipcodes = $sDbGeo->findZipCodeByCity('Kelkheim');

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#(\[\'<img[^\]]+?\])#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<span[^>]*>\s*([^<]+?)\s*(\s*<[^>]*>\s*)+\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $pattern = '#>([^<]+?)Uhr#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#Tel([^\']+)#';
            if (preg_match($pattern, $singleStore, $storePhoneMatch)) {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }

            $pattern = '#,\s*(\d+\.[^,]+?),\s*(\d+\.[^,]+?),\s*1\]#';
            if (preg_match($pattern, $singleStore, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                        ->setLongitude($geoMatch[2]);
            }

            $eStore->setAddress($storeAddressMatch[3], $storeAddressMatch[1])
                    ->setZipcode($sDbGeo->findZipCodeByCity(substr($eStore->getCity(), 0, 8)));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
