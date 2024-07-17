<?php

/*
 * Store Crawler für Meda Küchen (ID: 68892)
 */

class Crawler_Company_MedaKuechen_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.meda-kuechen.de/';
        $searchUrl = $baseUrl . 'kuechenstudios';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#a\s*href="(https:\/\/www\.meda-kuechen\.de\/kuechenstudios\/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (array_unique($storeUrlMatches[1]) as $singleStoreUrl) {
            $storeDetailUrl = $singleStoreUrl;
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#"locations":\[([^\]]+?)\]#s';
            if (!preg_match($pattern, $page, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $aInfos = json_decode($storeAddressMatch[1]);

            $pattern = '#fon:\s*<span[^>]*>\s*<a[^>]*href="tel:([^"]+?)"#';
            if (preg_match($pattern, $page, $storePhoneMatch)) {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }

            $pattern = '#fax:\s*<span[^>]*>\s*<a[^>]*href="tel:([^"]+?)"#';
            if (preg_match($pattern, $page, $storeFaxMatch)) {
                $eStore->setFaxNormalized($storeFaxMatch[1]);
            }

            $pattern = '#class="wpsl-opening-hours"[^>]*>(.+?)</table#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $eStore->setStreetAndStreetNumber($aInfos->address)
                ->setZipcode($aInfos->zip)
                ->setCity($aInfos->city)
                ->setLatitude($aInfos->lat)
                ->setLongitude($aInfos->lng)
                ->setStoreNumber($aInfos->id)
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
