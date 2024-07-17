<?php
/**
 * Store Crawler für Micasa CH (ID: 72170)
 */

class Crawler_Company_MicasaCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.micasa.ch/';
        $searchUrl = $baseUrl . 'jsapi/v1/de/stores';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->stores as $singleJStore) {
            if (!preg_match('#CH#', $singleJStore->location->country)) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->kst)
                ->setText(html_entity_decode($singleJStore->text))
                ->setPhoneNormalized($singleJStore->phone)
                ->setStreetAndStreetNumber($singleJStore->location->address)
                ->setZipcode($singleJStore->location->zip)
                ->setCity($singleJStore->location->city)
                ->setLatitude($singleJStore->location->latitude)
                ->setLongitude($singleJStore->location->longitude)
                ->setWebsite('https://www.micasa.ch' . $singleJStore->url);

            $sPage->open($searchUrl . '/store/' . $eStore->getStoreNumber());
            $jInfo = $sPage->getPage()->getResponseAsJson();

            $strTimes = '';
            if (count($jInfo->openingHours)) {
                foreach ($jInfo->openingHours as $singleDay) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }

                    $strTimes .= $singleDay->day . ' ' . $singleDay->openingTime;
                }
            }

            $eStore->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}