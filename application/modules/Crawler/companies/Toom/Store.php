<?php

/*
 * Store Crawler für toom Baumarkt (ID: 123)
 */

class Crawler_Company_Toom_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.toom-baumarkt.de/';
        $searchUrl = $baseUrl . 'public/api/markets';

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($sPage->getPage()->getResponseAsJson()->markets as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setCity($singleJStore->address->city)
                ->setLatitude($singleJStore->address->lat)
                ->setLongitude($singleJStore->address->lng)
                ->setStreetAndStreetNumber($singleJStore->address->street)
                ->setZipcode($singleJStore->address->zip)
                ->setEmail($singleJStore->email)
                ->setStoreNumber($singleJStore->id)
                ->setFaxNormalized($singleJStore->fax)
                ->setPhoneNormalized($singleJStore->phone)
                ->setWebsite($baseUrl . $singleJStore->link)
                ->setStoreHoursNormalized($this->getOpenings($singleJStore->openingTimes));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param $jsonOpenings
     * @return string
     */
    private function getOpenings($jsonOpenings)
    {
        $strTimes = '';
        $sep = ',';
        foreach ($jsonOpenings as $singleDay) {
            $strTimes .= $singleDay->label . ' ' . $singleDay->value->opening . '-' . $singleDay->value->closing . $sep;
        }
        return trim($strTimes, $sep);
    }

}
