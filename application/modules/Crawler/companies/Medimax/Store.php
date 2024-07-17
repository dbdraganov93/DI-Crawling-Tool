<?php

/**
 * Storecrawler für Medimax (ID: 101)
 */
class Crawler_Company_Medimax_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.medimax.de/';
        $searchUrl = $baseUrl . 'store-finder';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#window\.storeFinderComponent\s*=\s*([^\;]+?);#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $jStores = json_decode($storeListMatch[1])->stores;

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $strTimes = '';
            foreach ($singleJStore->openingHours as $singleDay) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }

                $strTimes .= $singleDay->day . ' ' . $singleDay->openingTime . '-' . $singleDay->closingTime;
            }

            $eStore->setStoreNumber($singleJStore->code)
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->long)
                ->setWebsite($baseUrl . preg_replace('#^\/#', '', $singleJStore->url))
                ->setStreetAndStreetNumber($singleJStore->address->street)
                ->setZipcode($singleJStore->address->zip)
                ->setCity($singleJStore->address->town)
                ->setPhoneNormalized($singleJStore->address->phone)
                ->setEmail($singleJStore->email)
                ->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}