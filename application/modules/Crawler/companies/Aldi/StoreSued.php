<?php

/**
 * Store Crawler für Aldi Süd (ID: 29)
 */
class Crawler_Company_Aldi_StoreSued extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $aWeekDays = array(
            'Montag',
            'Dienstag',
            'Mittwoch',
            'Donnerstag',
            'Freitag',
            'Samstag'
        );
        
        foreach (scandir(APPLICATION_PATH . '/../public/files/') as $singleFile) {
            if (preg_match('#stores_aldi#', $singleFile)) {
                $storesFile = APPLICATION_PATH . '/../public/files/' . $singleFile;
                break;
            }
        }
        $aStores = $sExcel->readFile($storesFile, true)->getElement(0)->getData();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStores as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $strTimes = '';
            foreach ($aWeekDays as $singleDay) {
                $timeFrom = $this->_convertTime($singleStore[$singleDay . ' von']);
                $timeTill = $this->_convertTime($singleStore[$singleDay . ' bis']);
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= $singleDay . ' ' . $timeFrom . '-' . $timeTill;
            }
            Zend_Debug::dump($singleStore);
            Zend_Debug::dump($strTimes);die;
            $eStore->setZipcode($singleStore['PLZ'])
                    ->setCity($singleStore['Ort'])
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleStore['Straße'])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleStore['Straße'])))
                    ->setStoreHours($sTimes->generateMjOpenings($strTimes))
                    ->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
    
    protected function _convertTime($strTime) {
        $time = $strTime * 24 * 100;
        if ($time % 100 == 0) {
            $strTimeReal = $time / 100 . ':00';
        } else {
            $strTimeReal = round($time / 100, 0, PHP_ROUND_HALF_DOWN) . ':' . ($time % 100) * 0.6;
        }
        return $strTimeReal;
    }
}