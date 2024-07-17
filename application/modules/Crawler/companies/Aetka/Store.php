<?php

/**
 * Store Crawler für Aetka (ID: 421)
 */
class Crawler_Company_Aetka_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $distributionNames = array(
            'aetka Anzeigen',
            'aetka Flyer',
            'aetkaSMART',
            'Telekom',
            'T-Partner',
            'Telekom Nord',
            'Telekom Nord-West',
            'Telekom Ost',
            'Telekom Ost2',
            'Telekom Süd',
            'Telekom West',
            'Vodafone',
            'vodafone Ost',
            'Telefonica',
            'Flyer Finanzierung',
            'Prospekt-Widget MIT aetka',
        );
     
        $cStore = new Marktjagd_Collection_Api_Store();
        
        $sFtp->connect($companyId);
        $localDirectory = $sFtp->generateLocalDownloadFolder($companyId);
        $localFileNameStores = $sFtp->downloadFtpToDir('Filialsuche.xls', $localDirectory);

        $aStores = $sExcel->readFile($localFileNameStores, true);
        $aStores = $aStores->getElements();       
                    
        foreach ($aStores[0]->getData() as $singleElement) {
            $distributions = array();
            
            foreach ($distributionNames as $distributionName){
                if (preg_match('#x#i', $singleElement[$distributionName])){
                    $distributions[] = $distributionName;
                }
            }                                          
                        
            $strTimes = 'Mo ' . $singleElement['MONTAG1'] . ', '
                    . 'Mo ' . $singleElement['MONTAG2'] . ', '
                    . 'Di ' . $singleElement['DIENSTAG1'] . ', '
                    . 'Di ' . $singleElement['DIENSTAG2'] . ', '
                    . 'Mi ' . $singleElement['MITTWOCH1'] . ', '
                    . 'Mi ' . $singleElement['MITTWOCH2'] . ', '
                    . 'Do ' . $singleElement['DONNERSTAG1'] . ', '
                    . 'Do ' . $singleElement['DONNERSTAG2'] . ', '
                    . 'Fr ' . $singleElement['FREITAG1'] . ', '
                    . 'Fr ' . $singleElement['FREITAG2'] . ', '
                    . 'Sa ' . $singleElement['SAMSTAG1'] . ', '
                    . 'Sa ' . $singleElement['SAMSTAG2'] . ', ';
            
            $strTitle = trim($singleElement['Name1']);
            if (!preg_match('#' . $singleElement['Name1'] . '#i', $singleElement['Name2'])
                    && !preg_match('#' . $singleElement['Name2'] . '#i', $singleElement['Name1'])) {
                $strTitle .= ' ' . trim($singleElement['Name2']);
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleElement['KNR'])
                    ->setTitle($strTitle)
                    ->setSubtitle('aetka')
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleElement['Street'])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleElement['Street'])))
                    ->setZipcode($singleElement['ZipCode'])
                    ->setCity($sAddress->normalizeCity($singleElement['City']))
                    ->setPhone($sAddress->normalizePhoneNumber($singleElement['Phone']))
                    ->setFax($sAddress->normalizePhoneNumber($singleElement['Fax']))
                    ->setEmail($singleElement['Email'])
                    ->setWebsite($singleElement['Web'])
                    ->setStoreHours($sTimes->generateMjOpenings(preg_replace('#[A-Z][a-z]\s,#', '', $strTimes)))
                    ->setDistribution(implode(',', $distributions));                                 
            
            $cStore->addElement($eStore, true);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
