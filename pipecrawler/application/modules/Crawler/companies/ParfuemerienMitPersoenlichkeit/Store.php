<?php

/* 
 * Store Crawler für Parfümerien mit Persönlichkeit (ID: 29119)
 */

class Crawler_Company_ParfuemerienMitPersoenlichkeit_Store extends Crawler_Generic_Company
{
    public function crawl($companyId) {
        $baseUrl = 'http://www.parfuemerien-mit-persoenlichkeit.de/';
        $searchUrl = $baseUrl . 'unsere-parfuemerien';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);
        
        $aParams = array(
            'isGeo' => 'true',
            'isAjax' => 'true',
            'pageNumber' => '0',
            'C003$dropDownListKilometer' => '100'
        );
        
        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 5; $i <= 17; $i += 0.5) {
            for ($j = 45; $j <= 56; $j += 0.5) {
                $aParams['latitude'] = $j;
                $aParams['longitude'] = $i;
                
                $sPage->open($searchUrl, $aParams);
                $jStores = $sPage->getPage()->getResponseAsJson();
                
                if (is_null($jStores) || $jStores->count == 0) {
                    continue;
                }
                
                foreach ($jStores->filialen as $singleJStore) {
                    if (!preg_match('#Deutschland#', $singleJStore->Land)) {
                        continue;
                    }
                    
                    $eStore = new Marktjagd_Entity_Api_Store();
                    
                    $eStore->setStoreNumber($singleJStore->ParfuemerieID)
                            ->setSubtitle($singleJStore->Name)
                            ->setWebsite($singleJStore->Shoplink)
                            ->setStreetAndStreetNumber($singleJStore->Strasse)
                            ->setCity($singleJStore->Ort)
                            ->setZipcode($singleJStore->Plz)
                            ->setPhoneNormalized($singleJStore->Telefon)
                            ->setFaxNormalized($singleJStore->Fax)
                            ->setEmail($singleJStore->EMail)
                            ->setLatitude($singleJStore->Latitude)
                            ->setLongitude($singleJStore->Longitude)
                            ->setStoreHoursNormalized($singleJStore->Oeffnungszeiten);
                    
                    if ($singleJStore->IsBeautyLounge) {
                        $eStore->setSection('BeautyLounge');
                    }
                    
                    $strBonuscards = '';
                    if ($singleJStore->IsWinCard) {
                        if (strlen($strBonuscards)) {
                            $strBonuscards .= ', ';
                        }
                        $strBonuscards .= 'Kundenkarte';
                    }
                    
                    if ($singleJStore->IsGiftCard) {
                        if (strlen($strBonuscards)) {
                            $strBonuscards .= ', ';
                        }
                        $strBonuscards .= 'Gutscheinkarte';
                    }
                    
                    $eStore->setBonusCard($strBonuscards);
                    
                    $cStores->addElement($eStore, TRUE);
                }
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}