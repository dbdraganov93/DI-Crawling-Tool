<?php
/**
 * Store Crawler für Topwell CH (ID: 72366)
 */

class Crawler_Company_TopwellCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.topwell.ch/';
        $searchUrl = $baseUrl . 'default/locationfinder/index/findLocations/?lat=47.05016819999999&lng=8.309307200000035&maxdistance=1000';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $aAddress = preg_split('#\s*<[^>]*>\s*#', $singleJStore->address);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setLatitude($singleJStore->latitude)
                ->setLongitude($singleJStore->longitude)
                ->setAddress($aAddress[0], $aAddress[1], 'ch')
                ->setPhoneNormalized($singleJStore->phone)
                ->setFaxNormalized($singleJStore->fax)
                ->setWebsite($singleJStore->changeWebsiteLink)
                ->setStoreHoursNormalized($singleJStore->businessHours)
                ->setTitle(preg_replace(array('#([a-zäöüß])([\dA-ZÄÖÜ])#', '#([a-zäöüß])\s+([a-zäöüß])#'), array('$1 $2', '$1$2'), $singleJStore->name));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}