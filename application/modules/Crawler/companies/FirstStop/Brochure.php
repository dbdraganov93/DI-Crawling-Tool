<?php

/**
 * Brochure Crawler für First Stop (ID: 29123)
 */
class Crawler_Company_FirstStop_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sMjFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPhpExcel = new Marktjagd_Service_Input_PhpExcel();
        $sAddress = new Marktjagd_Service_Text_Address();

        $cCompanyStores = $sApi->findStoresByCompany($companyId)->getElements();

        $sMjFtp->connect($companyId);
        $localPath = $sMjFtp->generateLocalDownloadFolder($companyId);
        $aFiles = $sMjFtp->listFiles();

        $assignmentFile = '';
        foreach ($aFiles as $singleFile) {
            if (preg_match('#(\.xls)$#', $singleFile)) {
                $assignmentFile = $singleFile;
                continue;
            }
            if (preg_match('#([0-9]{1}-Seiter)_(Equities|Partner)_?([A-Z])?_.+?\.pdf$#', $singleFile, $distMatch)) {
                if (array_key_exists(3, $distMatch)) {
                    $aPdfFilesEquities[$distMatch[3]] = $singleFile;
                } else {
                    $aPdfFilesPartner[$distMatch[1]] = $singleFile;
                }
            }
        }
        $localAssignmentFile = $sMjFtp->downloadFtpToDir($assignmentFile, $localPath);

        $aWorksheets = $sPhpExcel->readFile($localAssignmentFile, true);
        $aAssignmentPartner = $aWorksheets->getElement(0)->getData();
        $aAssignmentEquities = $aWorksheets->getElement(1)->getData();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aPdfFilesPartner as $singlePartnerPdfKey => $singlePartnerPdfValue) {
            $aStores = array();
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Freie Fahrt ins Frühjahr')
                    ->setStart('23.03.2015')
                    ->setEnd('30.04.2015')
                    ->setVisibleStart('23.03.2015')
                    ->setTags('Auto, Batterie, Felge, HU, Inspektion, Reifen, Scheibe, Scheibenwischer, Service, TÜV, Werkstatt, Öl')
                    ->setUrl($singlePartnerPdfValue)
                    ->setVariety('leaflet');
            foreach ($aAssignmentPartner as $singlePartner) {
                if (!preg_match('#x#', $singlePartner[$singlePartnerPdfKey])) {
                    continue;
                }
                foreach ($cCompanyStores as $eCompanyStore) {
                    if ($eCompanyStore->getZipCode() != $singlePartner['PLZ']) {
                        continue;
                    }
                    if ($sAddress->normalizeCity($eCompanyStore->getCity()) == $sAddress->normalizeCity($singlePartner['Ort'])) {
                        $aStores[] = $eCompanyStore->getStoreNumber();
                    }
                }
            }

            $eBrochure->setStoreNumber(implode(',', $aStores));

            $cBrochures->addElement($eBrochure);
        }
        
        foreach ($aPdfFilesEquities as $singlePartnerPdfKey => $singlePartnerPdfValue) {
            $aStores = array();
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Freie Fahrt ins Frühjahr')
                    ->setStart('23.03.2015')
                    ->setEnd('30.04.2015')
                    ->setVisibleStart('23.03.2015')
                    ->setTags('Auto, Batterie, Felge, HU, Inspektion, Reifen, Scheibe, Scheibenwischer, Service, TÜV, Werkstatt, Öl')
                    ->setUrl($singlePartnerPdfValue)
                    ->setVariety('leaflet');
            foreach ($aAssignmentEquities as $singlePartner) {
                if (!preg_match('#' . $singlePartnerPdfKey . '#', $singlePartner['Eindruck Variante'])) {
                    continue;
                }
                foreach ($cCompanyStores as $eCompanyStore) {
                    if ($eCompanyStore->getZipCode() != $singlePartner['PLZ']) {
                        continue;
                    }
                    if ($sAddress->normalizeCity($eCompanyStore->getCity()) == $sAddress->normalizeCity($singlePartner['Ort'])) {
                        $aStores[] = $eCompanyStore->getStoreNumber();
                    }
                }
            }

            $eBrochure->setStoreNumber(implode(',', $aStores));

            $cBrochures->addElement($eBrochure);
        }

        $sMjFtp->transformCollection($cBrochures, '/' . $companyId . '/', 'brochures', $localPath);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
