<?php

/**
 * Store Crawler für PC Spezialist (ID: 22239)
 */
class Crawler_Company_PcSpezialist_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://search.synaxon.de/';
        $searchUrl = $baseUrl . 'geolocation/search/partner/?id=pcspezialist';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $aWeekdays = array(
            '1' => 'Mo',
            '2' => 'Di',
            '3' => 'Mi',
            '4' => 'Do',
            '5' => 'Fr',
            '6' => 'Sa'
        );
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach($jStores as $singleJStore) {
            if (!$singleJStore->sichtbar || !preg_match('#^\d#', $singleJStore->plz)) {
                continue;
            }
            
            $strTimes = '';
            foreach ($singleJStore->oeffnungszeiten as $singleDay) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= $aWeekdays[$singleDay->wochentag] . ' ' . $singleDay->von1 . '-' . $singleDay->bis1;
                
                if (!is_null($singleDay->von2) && !is_null($singleDay->bis2)) {
                    $strTimes .= ',' . $aWeekdays[$singleDay->wochentag] . ' ' . $singleDay->von2 . '-' . $singleDay->bis2;
                }
            }
                        
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->partnernr)
                    ->setWebsite($singleJStore->urllink)
                    ->setSubtitle($singleJStore->partnername)
                    ->setStreetAndStreetNumber($singleJStore->strasse)
                    ->setZipcode($singleJStore->plz)
                    ->setCity($singleJStore->ort)
                    ->setPhoneNormalized($singleJStore->telefon)
                    ->setEmail($singleJStore->email)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setStoreHoursNormalized($strTimes);
            
            $cStores->addElement($eStore, TRUE);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}