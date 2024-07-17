<?php

/**
 * Store Crawler für Louis Motorrad (ID: 72390)s
 */

class Crawler_Company_Louis_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.louis.de';
        $storeUrl = $baseUrl . '/service/filialen/info/maps/show-map/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($storeUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#data-stores=\"(\[.+?\])\"#';
        if (!preg_match($pattern, $page, $storeList)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $jStores = json_decode($storeList[1]);

        $cStore = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleStore) {
            if (!preg_match('#^\d{5}#', trim($singleStore->address->city))) {
                continue;
            }

            $strTime = '';
            $strService = '';
            $eStore = new Marktjagd_Entity_Api_Store();
            foreach ($singleStore->openingTimes as $openingTime) {
                if (strlen($strTime)) {
                    $strTime .= ', ';
                }
                $strTime .= $openingTime->day . ' ' . $openingTime->value;
            }
            foreach ($singleStore->attributes as $service) {
                if (strlen($strService)) {
                    $strService .= ', ';
                }
                $strService .= $service->desc;
            }

            $eStore->setStoreNumber($singleStore->id)
                ->setLatitude($singleStore->lat)
                ->setLongitude($singleStore->lng)
                ->setTitle($singleStore->title)
                ->setStreetAndStreetNumber($singleStore->address->street)
                ->setZipcodeAndCity($singleStore->address->city)
                ->setWebsite($singleStore->url)
                ->setStoreHoursNormalized($strTime)
                ->setService($strService);

            $cStore->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
