<?php
/**
 * Store Crawler für Reifen 1+ (ID: 72353)
 */

class Crawler_Company_Reifen1Plus_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Teilnehmer\.xls#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $aStoreData = $sExcel->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreData as $singleStore) {
            $strTime = 'Mo-Fr ' . $singleStore['Montag-Freitag'];
            if (strlen($singleStore['Montag-Freitag_2'])) {
                $strTime .= ',Mo-Fr ' . $singleStore['Montag-Freitag_2'];
            }

            $strTime .= 'Sa ' . $singleStore['Samstag'];

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setTitle($singleStore['Firma 1'])
                ->setStreetAndStreetNumber($singleStore['Straße'] . ' ' . $singleStore['Hausnummer'])
                ->setZipcode($singleStore['PLZ'])
                ->setCity($singleStore['Ort'])
                ->setStoreHoursNormalized($strTime)
                ->setPhoneNormalized($singleStore['Telefon '])
                ->setFaxNormalized($singleStore['Fax'])
                ->setEmail($singleStore['email'])
                ->setWebsite($singleStore['url']);

            if (strlen($eStore->getWebsite()) && !preg_match('#^http#', $eStore->getWebsite())) {
                $eStore->setWebsite('http://' . $singleStore['url']);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}