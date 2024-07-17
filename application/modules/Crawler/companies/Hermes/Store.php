<?php

/**
 * Store Crawler für Hermes (ID: 71540)
 */
class Crawler_Company_Hermes_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.hermespaketshop.de/';
        $searchUrl = $baseUrl . 'paketshopfinder/apiproxy.php/findParcelShopsByLocation'
                . '?lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
                . '&lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON
                . '&maxResult=1000&acceptsSuitcases=false&hasBoxes=false&ident=false&openAtDayOfWeek=&openAtTime=&mandantId=DE&consumerName=HLG000008&consumerPassword=b148ad05953889c9';
        
        $detailUrl = 'paketshopfinder/apiproxy.php/getParcelShopById?mandantId=DE&consumerName=HLG000008&consumerPassword=b148ad05953889c9&shopId=';        
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sAddress = new Marktjagd_Service_Text_Address();

        $aLinks = $sGen->generateUrl($searchUrl, 'coords', 0.1);
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $this->_logger->info("Insgesamt: " . count($aLinks));
        
        foreach ($aLinks as $singleLink) {
            $sPage->open($singleLink);
            $jStores = $sPage->getPage()->getResponseAsJson();
            
            $this->_logger->info("open: " . $singleLink);
            
            foreach ($jStores as $jSingleStore) {
                
                if ((string) $jSingleStore->shopId == '619954'){
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setTitle('Hermes Paketshop');
                
                $eStore->setStoreNumber((string) $jSingleStore->shopId)
                        ->setLatitude((string) $jSingleStore->lat)
                        ->setLongitude((string) $jSingleStore->lng)
                        ->setSubtitle($jSingleStore->description)
                        ->setStreet($jSingleStore->address->street)
                        ->setStreetNumber($jSingleStore->address->houseNumber)
                        ->setZipcode($jSingleStore->address->postCode)
                        ->setCity($jSingleStore->address->city);
                
                $cStores->addElement($eStore, true);   
            }
        }
        
        $cfStores = new Marktjagd_Collection_Api_Store();

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect($companyId);
        $filePath = $sFtp->downloadFtpToCompanyDir('paketshop.xls', $companyId);

        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $aData = $sExcel->readFile($filePath, true)->getElement(0)->getData();

        /* @var $eStore Marktjagd_Entity_Api_Store */
        foreach ($cStores->getElements() as $eStore){
            $sPage->open($baseUrl . $detailUrl . $eStore->getStoreNumber());
            $jStore = $sPage->getPage()->getResponseAsJson();
            
            $hoursAr = array();
            foreach ($jStore->businessHours as $hours){
                $hoursAr[] = $hours->dayOfWeek . ' ' . $hours->openFrom . '-' . $hours->openTill;
            }

            $eStore->setStoreHoursNormalized(implode(',', $hoursAr, 'text', true));

            $eStore->setPhone($jStore->telephoneCode . ' ' . $jStore->telephone)
                    ->setWebsite(pre_replace('#(https?:\/\/)([^\/]+?\/\/)?(.+)#', '$1$3', $jStore->url))
                    ->setEmail($jStore->email);
                        
            // Services
            $serviceAr = array();            
            
            if ($jStore->acceptsSuitcases){
                $serviceAr[] = 'Reisegepäckabgabe';                
            }
            
            if ($jStore->hasBoxes){
                $serviceAr[] = 'Hermes Verpackungen';                
            }
            
            if ($jStore->ident){
                $serviceAr[] = 'Identitätsprüfung';                
            }
            
            $eStore->setService(implode(',', $serviceAr));

            foreach ($aData as $dataLine) {
                if (!$dataLine['Urlaub von']
                    || !$dataLine['Urlaub bis']
                    || $dataLine['PLZ'] != $eStore->getZipcode()) {
                    continue;
                }

                $streetFromEntity = $sAddress->normalizeStreet($eStore->getStreet());
                $streetFromCsv = $sAddress->normalizeStreet(
                    $sAddress->extractAddressPart('street', $dataLine['Strasse']));

                if ($streetFromEntity == $streetFromCsv) {
                    $eStore->setStoreHoursNotes('Urlaub vom ' . $dataLine['Urlaub von'] . ' bis ' . $dataLine['Urlaub bis']);
                }
            }
            
            $cfStores->addElement($eStore, TRUE);
        }        
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cfStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}