<?php

/**
 * Store Crawler für Opel (ID: 68847)
 */
class Crawler_Company_Opel_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        
        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        
        $pattern = '#20170518_Teilnehmer_Opel Aktuell\.csv#';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match($pattern, $singleFile)) {
                $localXlsFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }
        
        $aData = $sExcel->readFile($localXlsFile, TRUE, ';')->getElement(0)->getData();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleData) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleData['PKW Service'])
                    ->setSubtitle($singleData['Firmenname'])
                    ->setStreetAndStreetNumber($singleData['Straße'])
                    ->setZipcode($singleData['PLZ'])
                    ->setCity($singleData['Standort'])
                    ->setPhoneNormalized($singleData['Telefon']);
            
            if (preg_match('#Ja#', $singleData['Gesamtbestellung abgeschlossen'])) {
                $eStore->setDistribution('Kampagne');
            }
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
