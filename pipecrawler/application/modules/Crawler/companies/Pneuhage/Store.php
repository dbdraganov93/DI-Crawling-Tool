<?php

/**
 * Storecrawler für Pneuhage (ID: 29002)
 *
 */
class Crawler_Company_Pneuhage_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sMjFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPhpExcel = new Marktjagd_Service_Input_PhpExcel();
        $cStore = new Marktjagd_Collection_Api_Store();
        $sTimes = new Marktjagd_Service_Text_Times();

        if (strtotime('now') <= strtotime('15.03.' . $sTimes->getWeeksYear())
            && strtotime('now') >= strtotime('01.09.' . ($sTimes->getWeeksYear()-1))) {
            $strSeason = 'NEBENSAISON ';
        } else {
            $strSeason = 'HAUPTSAISON ';
        }

        $sMjFtp->connect(29002);
        $localPath = $sMjFtp->generateLocalDownloadFolder($companyId);
        foreach ($sMjFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xls#', $singleFile)) {
                $localAssignmentFile = $sMjFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $aWorksheets = $sPhpExcel->readFile($localAssignmentFile, true);
        $aAssignment = $aWorksheets->getElement(0)->getData();

        $count = 0;
        foreach ($aAssignment as $singleStore) {
            if ($companyId == 29002 && !preg_match('#pneuhage#i', $singleStore['Firmenname'])) {
                continue;
            }

            if ($companyId == 70838 && !preg_match('#ehrhardt#i', $singleStore['Firmenname'])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleStore['Filiale'])
                ->setSubtitle($singleStore['Werbeanschrift'])
                ->setCity($singleStore['Ort'])
                ->setZipcode($singleStore['PLZ'])
                ->setStreet($singleStore['Straße'])
                ->setStreetNumber($singleStore['Nr'])
                ->setEmail($singleStore['E-Mail'])
                ->setStoreHoursNormalized('Mo-Fr ' . $singleStore[$strSeason . "Öffnungszeiten MO-FR"] . ', ' . 'Sa ' . $singleStore[$strSeason . "Öffnungszeiten Samstag"])
                ->setStoreHoursNotes($singleStore['Öffnungszeiten Freitext'])
                ->setWebsite($singleStore['URL'] . '?campaign=DB/PN/B1/Link/Allgemein/FS2018')
                ->setPhoneNormalized('0' . $singleStore['Vorwahl'] . $singleStore['Telefon'])
                ->setFaxNormalized('0' . $singleStore['Vorwahl'] . $singleStore['Fax']);

            if (!is_array($singleStore['SEO-Text']) && strlen($singleStore['SEO-Text']) > 16) {
                $eStore->setText($singleStore['SEO-Text']);
            }

            $serviceCol = false;
            $service = array();
            foreach ($singleStore as $key => $value) {
                if ($key == 'Sonderleistungen Freitext') {
                    // nach der Spalte Sonderleistungen Freitext enden die interessanten Service-Leistungen
                    $serviceCol = false;
                }

                if ($serviceCol) {
                    if ($value == 'x') {
                        $service[] = $key;
                    }
                }

                if ($key == 'Öffnungszeiten Freitext') {
                    // nach der Spalte Öffnungszeiten Freitext beginnen die Service-Leistungen
                    $serviceCol = true;
                }
            }

            if (count($service)) {
                $eStore->setService(implode(', ', $service));
            }

            $cStore->addElement($eStore, true);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}