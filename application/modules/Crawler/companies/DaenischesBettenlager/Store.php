<?php
class Crawler_Company_DaenischesBettenlager_Store extends Crawler_Generic_Company
{
    public function crawl($companyId) {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sTransfer = new Marktjagd_Service_Transfer_Http();
        $remoteFilename = 'placesexport.php';
        $storeUrl = 'http://www.daenischesbettenlager.com/typo3conf/ext/dbl_filialen/' . $remoteFilename;
        $localPath = $sTransfer->generateLocalDownloadFolder($companyId);
        $localFileName = 'stores_dbl.csv';
        $sTransfer->getRemoteFile($storeUrl, $localPath);

        // Bereinigen der Daten, da PHP-Errormeldungen in CSV stehen
        $data = file($localPath . $remoteFilename);
        $out = array();

        foreach($data as $line) {
            if(substr($line, 0,1) != '<') {
                $line = str_replace("Complete", "", $line);
                $out[] = $line;
            }
        }

        $fp = fopen($localPath . $localFileName, "w+");
        flock($fp, LOCK_EX);
        foreach($out as $line) {
            fwrite($fp, $line);
        }
        flock($fp, LOCK_UN);
        fclose($fp);

        $sPhpExcel = new Marktjagd_Service_Input_PhpExcel();
        $worksheet = $sPhpExcel->readFile($localPath . $localFileName, true, ';');
        $aData = $worksheet->getElement(0)->getData();

        foreach ($aData as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleStore['Store Code']);
            $eStore->setStreetAndStreetNumber($singleStore['Address Line 1']);
            $eStore->setCity($singleStore['Locality']);
            $eStore->setZipcode($singleStore['Postal Code']);
            $eStore->setPhoneNormalized($singleStore['Primary phone']);

            $sOpenings = '';
            if (strlen($singleStore['Monday hours'])) {
                $sOpenings .= 'Mo ' . $singleStore['Monday hours'];
            }

            if (strlen($singleStore['Tuesday hours'])) {
                $sOpenings .= 'Di ' . $singleStore['Tuesday hours'];
            }
            if (strlen($singleStore['Wednesday hours'])) {
                $sOpenings .= 'Mi ' . $singleStore['Wednesday hours'];
            }
            if (strlen($singleStore['Thursday hours'])) {
                $sOpenings .= 'Do ' . $singleStore['Thursday hours'];
            }
            if (strlen($singleStore['Friday hours'])) {
                $sOpenings .= 'Fr ' . $singleStore['Friday hours'];
            }
            if (strlen($singleStore['Saturday hours'])) {
                $sOpenings .= 'Sa ' . $singleStore['Saturday hours'];
            }

            $eStore->setStoreHoursNormalized($sOpenings);
            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
