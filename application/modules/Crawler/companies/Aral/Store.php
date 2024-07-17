<?php

/**
 * Store Crawler für Aral (ID: 67192)
 */
class Crawler_Company_Aral_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sAddress = new Marktjagd_Service_Text_Address();

        $serviceVals = array(
            "PETITBISTRO" => "PetitBistro",
            "NATURAL_GAS" => "Erdgas",
            "AUTOGAS" => "Autogas",
            "ULTIMATE_FUELS" => "Ultimate-Kraftstoffe",
            "WASHING_FACILITY" => "Waschanlage",
            "DIESEL_FOR_TRUCKS" => "LKW Diesel",
            "CASH_MACHINE" => "Geldautomat",
            "TOLL_STATION" => "Mautstation",
            "REST_STOP" => "Autohof",
            "ADBLUE" => "AdBlue Zapfsäule"
        );
        
        $sFtp->connect($companyId);
        $localDirectory = $sFtp->generateLocalDownloadFolder($companyId);
        $localFileNameStores = $sFtp->downloadFtpToDir('station_data.csv', $localDirectory);
        
        $aStores = $sExcel->readFile($localFileNameStores, true, ';');
        $aStores = $aStores->getElement(0)->getData();
        
        $cStores = new Marktjagd_Collection_Api_Store();        
        
        foreach ($aStores as $singleElement) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleElement['FILLING_STATION_NUMBER'])
                    ->setLongitude(trim(str_replace(',','.', $singleElement['GPS_X'])))
                    ->setLatitude(trim(str_replace(',','.', $singleElement['GPS_Y'])))
                    ->setSubtitle($singleElement['NAME'])
                    ->setStreet($sAddress->extractAddressPart('street', $singleElement['STREET']))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $singleElement['STREET']))
                    ->setZipcode($singleElement['ZIP_CODE'])
                    ->setCity($singleElement['CITY'])
                    ->setPhone($sAddress->normalizePhoneNumber($singleElement['TELEPHONE']))
                    ->setFax($sAddress->normalizePhoneNumber($singleElement['FAX']));

            $services = array();
            foreach ($serviceVals as $keyVal => $serviceVal){
                if (array_key_exists($keyVal, $singleElement) && $singleElement[$keyVal] == "1"){
                    $services[] = $serviceVal;
                }
            }
            
            if (count($services)){
                $eStore->setService(implode(', ', $services));
            }
            
            if (array_key_exists('PAYBACK', $singleElement) && $singleElement['PAYBACK'] == "1"){
                $eStore->setBonusCard('PAYBACK');
            }

            $storeHoursStr = $singleElement["OPENING_HOURS"];
            $storeHoursStr = preg_replace('#\s#', ',', $storeHoursStr);
            $storeHoursStr = preg_replace('#\:#', ' ', $storeHoursStr);
            $storeHoursStr = preg_replace('#([0-9]{2})([0-9]{2})#', '$1:$2', $storeHoursStr);
            $eStore->setStoreHours($storeHoursStr);

            $cStores->addElement($eStore, true);         
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}