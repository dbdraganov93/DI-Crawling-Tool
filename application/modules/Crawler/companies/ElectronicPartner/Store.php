<?php

/**
 * Store Crawler für EP: Electronic Partner (ID: 85)
 */
class Crawler_Company_ElectronicPartner_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.ep.de';
        $searchUrl = $baseUrl . '/store-finder';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#window\.storeFinderComponent\s*=\s*(\{.+?\});\s*<\/script>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $jStores = json_decode($storeListMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->stores as $singleJStore) {
            $strTime = '';
            foreach ($singleJStore->openingHours as $openingHours) {
                if (strlen($strTime)) {
                    $strTime .= ',';
                }
                if (strlen($openingHours->startPauseTime) && strlen($openingHours->endPauseTime)) {
                    $strTime .= $openingHours->day . ' ' . $openingHours->openingTime . '-' . $openingHours->startPauseTime;
                    $strTime .= ',' . $openingHours->day . ' ' . $openingHours->endPauseTime . '-' . $openingHours->closingTime;
                } else {
                    $strTime .= $openingHours->day . ' ' . $openingHours->openingTime . '-' . $openingHours->closingTime;
                }
            }


            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->code)
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->long)
                ->setTitle($singleJStore->name)
                ->setWebsite($baseUrl . $singleJStore->url)
                ->setLogo($singleJStore->logoUrl)
                ->setStreetAndStreetNumber($singleJStore->address->street)
                ->setZipcode($singleJStore->address->zip)
                ->setCity($singleJStore->address->town)
                ->setPhoneNormalized($singleJStore->address->phone)
                ->setEmail($singleJStore->email)
                ->setStoreHoursNormalized($strTime);

            $cStores->addElement($eStore);
        }


        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $filename = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($filename);
    }

}
