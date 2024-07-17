<?php

/**
 * Store Crawler für Degussa Bank (ID: 71662)
 */
class Crawler_Company_DegussaBank_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.degussa-bank.de/';
        $searchUrl = $baseUrl . 'bank-shop-suche?p_p_id=zweigstellensucheportlet_WAR_zweigstellensucheportlet&'
                . 'p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_cacheability=cacheLevelPage&p_p_col_id=column-1&'
                . 'p_p_col_pos=2&p_p_col_count=3&_zweigstellensucheportlet_WAR_zweigstellensucheportlet_type=markersUrl';
        $detailUrl = $baseUrl . 'bank-shop-suche?p_p_id=zweigstellensucheportlet_WAR_zweigstellensucheportlet&p_p_lifecycle=2&'
                . 'p_p_state=normal&p_p_mode=view&p_p_cacheability=cacheLevelPage&p_p_col_id=column-1&p_p_col_pos=2&'
                . 'p_p_col_count=3&_zweigstellensucheportlet_WAR_zweigstellensucheportlet_type=layerUrl&&id=';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->markers as $singleJStore) {
            if (!$singleJStore->public) {
                continue;
            }
            
            $sPage->open($detailUrl . $singleJStore->id);
            $jDetailStore = $sPage->getPage()->getResponseAsJson();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $aAddress = preg_split('#\s*<[^>]*>\s*#', $jDetailStore->addressPublic);
            
            $eStore->setStoreNumber($singleJStore->id)
                    ->setLongitude($singleJStore->geo->lng)
                    ->setLatitude($singleJStore->geo->lat)
                    ->setPhone($sAddress->normalizePhoneNumber($jDetailStore->phone))
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress) - 2])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress) - 2])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress) - 1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[count($aAddress) - 1]));
            
            $cStores->addElement($eStore, true);
        }
                
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
