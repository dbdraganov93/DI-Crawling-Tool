<?php

/**
 * Store Crawler für Nordsee (ID: 28994)
 */
class Crawler_Company_Nordsee_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.nordsee.com/';
        $searchUrl = $baseUrl . 'api/vendors.php';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage->open($searchUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json as $s => $item) {
            if ('Germany' != $item->country) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($item->number);
            $eStore->setLatitude($item->lat);
            $eStore->setLongitude($item->lng);
            $eStore->setPhoneNormalized($item->phone);
            $eStore->setFaxNormalized($item->fax);
            $eStore->setStreetAndStreetNumber($item->street);
            $eStore->setZipcode($item->zip);
            $eStore->setCity($item->city);

            // hours
            if (is_array($item->opening)) {
                $hours = array();
                foreach ($item->opening as $o) {
                    $h = substr($o->day, 0, 2) . ' ' . $o->opening;
                    $h = preg_replace('#\s*-\s*#', '-', $h);
                    $hours[] = $h;
                }
                $eStore->setStoreHoursNormalized(implode(',', $hours));
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
