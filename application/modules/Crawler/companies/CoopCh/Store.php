<?php

/* 
 * Store Crawler für coop.ch (ID: )
 */

class Crawler_Company_CoopCh_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.coop.ch/';
        $openingsUrl = $baseUrl . 'content/vstinfov2/de/detail.getvstopeninghours.json?language=de&id=';
        $filter = '';

        if ($companyId == 72139) {
            $filter = 'retail';
        } else if ($companyId == 72149) {
            $filter = 'toptip';
        } else if ($companyId == 72151) {
            $filter = 'christ';
        } else if ($companyId == 72152) {
            $filter = 'bh';
        } else if ($companyId == 72153) {
            $filter = 'city';
        } else if ($companyId == 72154) {
            $filter = 'impo';
        } else if ($companyId == 72155) {
            $filter = 'lumimart';
        }

        $searchUrl = $baseUrl . 'de/services/standorte-und-oeffnungszeiten.getvstlist.json?'
            . 'lat=47.543427&lng=7.598133599999983&start=1&end=5000&filterFormat=' . $filter
            . '&filterAttribute=&filterOpen=false&gasIndex=0';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->vstList as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->betriebsNummerId->id)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setZipcode($singleJStore->plz)
                    ->setStreetAndStreetNumber($singleJStore->strasse . ' ' . $singleJStore->hausnummer, 'CH')
                    ->setCity($singleJStore->ort)
                    ->setWebsite($baseUrl . 'de/services/standorte-und-oeffnungszeiten/detail.html/id=' . $eStore->getStoreNumber());
            
            $sPage->open($openingsUrl . $eStore->getStoreNumber());
            $json = $sPage->getPage()->getResponseAsJson();

            $aTimes = array();

            foreach ($json->hours as $day) {
                if ($day->holidayNr == '') {
                    $aTimes[$day->desc] = $day->desc . ' ' . $day->time;
                }

                if (count($aTimes) == 7) {
                    break;
                }
            }

            $eStore->setStoreHoursNormalized(implode(', ', $aTimes));
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}