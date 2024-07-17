<?php

/**
 * Store Crawler für Westlotto (ID: 71772)
 */
class Crawler_Company_WestLotto_StoreFTP extends Crawler_Generic_Company {

    public function crawl($companyId) {
        ini_set('memory_limit', '1G');
        
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
                
        $weekdayMap = array(
            'Montag' => 'Mo',
            'Dienstag' => 'Di',
            'Mittwoch' => 'Mi',
            'Donnerstag' => 'Do',
            'Freitag' => 'Fr',
            'Samstag' => 'Sa',
            'Sonntag' => 'So',            
        );
                
        $sFtp->connect($companyId);

        $localStoreFile = $sFtp->downloadFtpToCompanyDir('ast_stores.csv', $companyId);                               
        
        $storeData = $sExcel->readFile($localStoreFile, true, ';');
        $storeData = $storeData->getElements();
                
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($storeData[0]->getData() as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleStore['VSt-Nummer'])
                    ->setSubtitle($singleStore['VSt-Name'])
                    ->setStreet($singleStore['VSt-Anschrift - Straße'])
                    ->setStreetNumber($singleStore['VSt-Anschrift - Hausnummer'])
                    ->setZipcode($singleStore['VSt-Anschrift - PLZ'])
                    ->setCity($singleStore['VSt-Anschrift - Ort'])
                    ->setPhone($singleStore['VSt-Anschrift - Festnetz']);
            
            if (strlen($singleStore['VSt-Anschrift - Zusatz'])){
                $eStore->setSubtitle($eStore->getSubtitle() . ' (' . $singleStore['VSt-Anschrift - Zusatz'] . ')');
            }
            
            $hoursAr = array();
            foreach ($weekdayMap as $dayName => $dayShortName){
                if ($singleStore['Öffnungszeit - ' . $dayName . ' von'] != '00:00' && $singleStore['Öffnungszeit - ' . $dayName . ' bis'] != '00:00'){
                    if (strlen($singleStore['Öffnungszeit - ' . $dayName . ' Pause von'])){
                        $hoursAr[] = $dayShortName . ' ' . $singleStore['Öffnungszeit - ' . $dayName . ' von'] . '-' . $singleStore['Öffnungszeit - ' . $dayName . ' Pause von'];
                        $hoursAr[] = $dayShortName . ' ' . $singleStore['Öffnungszeit - ' . $dayName . ' Pause bis'] . '-' . $singleStore['Öffnungszeit - ' . $dayName . ' bis'];
                    } else {                                            
                        if ($singleStore['Öffnungszeit - ' . $dayName . ' bis'] == '00:00'){
                            $singleStore['Öffnungszeit - ' . $dayName . ' bis'] = '24:00';
                        }
                        $hoursAr[] = $dayShortName . ' ' . $singleStore['Öffnungszeit - ' . $dayName . ' von'] . '-' . $singleStore['Öffnungszeit - ' . $dayName . ' bis'];
                    }
                }
            }
            
            $eStore->setStoreHours($sTimes->generateMjOpenings(implode(',', $hoursAr), 'text', true));
                        
            if ($eStore->getStoreNumber() == 204721){
                $eStore->setPhone('');
            }
            
            
            $cStores->addElement($eStore);
        }     
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }   
}
