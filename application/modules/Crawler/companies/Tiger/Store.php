<?php

/**
 * Store Crawler für Tiger (ID: 71354)
 */
class Crawler_Company_Tiger_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.tiger-stores.com/';
        $searchUrl = $baseUrl . 'services/stores.php';
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sTextFormat = new Marktjagd_Service_Text_TextFormat();

        $sPage = new Marktjagd_Service_Input_Page(true);
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->stores as $singleJStore) {
            if (!preg_match('#(Germany|Deutschland)#', $singleJStore->country)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $strTime = '';
//            foreach ($singleJStore->times as $singleTime) {
//                if (strlen($strTime)) {
//                    $strTime .= ',';
//                }
//                $strTime .= $singleTime->day . ' ' . $singleTime->time;
//            }
            
            $eStore->setStreetAndStreetNumber($singleJStore->address)
                   ->setZipcodeAndCity($singleJStore->city)
                   ->setLatitude($singleJStore->geocode->lat)
                   ->setLongitude($singleJStore->geocode->lng);
//                    ->setStoreNumber($singleJStore->id)
//                    ->setSubtitle($sTextFormat->htmlDecode($singleJStore->name))
//                    ->setStreet($sTextFormat->htmlDecode($sAddress->extractAddressPart('street', $singleJStore->address)))
//                    ->setStreetNumber($sTextFormat->htmlDecode($sAddress->extractAddressPart('streetnumber', $singleJStore->address)))
//                    ->setCity($sAddress->extractAddressPart('city', $singleJStore->city))
//                    ->setZipcode($sAddress->extractAddressPart('zipcode', $singleJStore->city))
//                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->phone))
//                    ->setEmail($singleJStore->email)
//                    ->setLatitude($singleJStore->geocode->lat)
//                    ->setLongitude($singleJStore->geocode->lng)
//                    ->setWebsite($singleJStore->pg_urlpart_full)
//                    ->setStoreHours($sTimes->generateMjOpenings($strTime));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
