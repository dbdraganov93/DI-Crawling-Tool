<?php

/**
 * Store Crawler für Citroen (ID: 68846)
 */
class Crawler_Company_Citroen_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.citroen.de/';
        $searchUrl = $baseUrl . '_/Layout_Citroen_PointsDeVente/getStoreList?'
                . 'area=1000&attribut=&lat=50&long=10';
        $aStoreIds = array();
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect($companyId);
        $localPath = $sFtp->downloadFtpToCompanyDir('PSAR_Liste_NL-Offnungszeiten.xls', $companyId);
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $worksheet = $sExcel->readFile($localPath, true);
        $aLines = $worksheet->getElement(0)->getData();

        $cStoresAdditionalInfos = new Marktjagd_Collection_Api_Store();
        foreach ($aLines as $aLine) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreetAndStreetNumber($aLine['Adresszeile 1']);
            $eStore->setZipcode($aLine['Postleitzahl']);
            $eStore->setCity($aLine['Ort']);
            $eStore->setPhoneNormalized($aLine['Primäre Telefonnummer']);
            $eStore->setWebsite($aLine['Webseite']);
            $sOpening = 'Mo ' . $aLine["Öffnungszeiten (montags)"] . ', '
                . 'Di ' . $aLine["Öffnungszeiten (dienstags)"] . ', '
                . 'Mi ' . $aLine["Öffnungszeiten (mittwochs)"] . ', '
                . 'Do ' . $aLine["Öffnungszeiten (donnerstags)"] . ', '
                . 'Fr ' . $aLine["Öffnungszeiten (freitags)"] . ', '
                . 'Sa ' . $aLine["Öffnungszeiten (samstags)"];
            $sOpening = $sTimes->convertAmPmTo24Hours($sOpening);
            $eStore->setStoreHoursNormalized($sOpening);
            $cStoresAdditionalInfos->addElement($eStore);
        }

        if (!$sPage->open($searchUrl)) {
            throw new Exception ($companyId . ': unable to open store list page.');
        }
        
        $page = $sPage->getPage()->getResponseBody();
        $jStoreLinks = json_decode($page);
        
        foreach ($jStoreLinks as $jSingleLink) {
            $aStoreIds[] = $jSingleLink->id;
        }
        
        $searchUrl = $baseUrl . '_/Layout_Citroen_PointsDeVente/getDealer?id=';
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($aStoreIds as $singleStoreId) {
            if (!$sPage->open($searchUrl . $singleStoreId)) {
                $this->_logger->err($companyId . ': unable to open store page for no ' . $singleStoreId);
                continue;
            }
            
            $page = $sPage->getPage()->getResponseBody();
            $jSingleStore = json_decode($page);
            
            if ($jSingleStore->RRDI == '01X') {
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $jSingleStore->address);
            $strServices = '';
            foreach ($jSingleStore->servicesMob as $singleService) {
                if (strlen($strServices)) {
                    $strServices .= ', ';
                }
                $strServices .= $singleService->label;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($jSingleStore->RRDI)
                    ->setTitle($jSingleStore->name)
                    ->setStreetAndStreetNumber($aAddress[0])
                    ->setZipcodeAndCity($aAddress[1])
                    ->setPhoneNormalized($jSingleStore->phone)
                    ->setFaxNormalized($jSingleStore->fax)
                    ->setEmail($jSingleStore->email)
                    ->setWebsite($jSingleStore->web)
                    ->setLatitude($jSingleStore->lat)
                    ->setLongitude($jSingleStore->lng)
                    ->setService($strServices)
                    ->setStoreHoursNormalized($jSingleStore->timetable);
            $cStores->addElement($eStore, TRUE);
        }

        $sCompare = new Marktjagd_Service_Compare_Collection_Store();
        $cStoresUpdated = $sCompare->updateStores($cStores, $cStoresAdditionalInfos);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStoresUpdated);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}