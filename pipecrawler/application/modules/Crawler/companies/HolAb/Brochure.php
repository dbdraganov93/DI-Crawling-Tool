<?php

/**
 * Prospekt Crawler für Hol' Ab Getränkemarkt (ID 22132)
 */
class Crawler_Company_HolAb_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sAddress = new Marktjagd_Service_Text_Address();

        $aApiStores = $sApi->findAllStoresForCompany($companyId);

        $aFtpBrochures = array();

        $sFtp->connect($companyId);
        $localFolder = $sFtp->generateLocalDownloadFolder($companyId);

        $nextWeek = date('W', strtotime('next week'));

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#KW ' . $nextWeek . '.+?\.xlsx?$#', $singleFile)) {
                $localXlsFile = $sFtp->downloadFtpToDir($singleFile, $localFolder);
                continue;
            }
            if (preg_match('#([0-9]+).+?KW.+?(_Vorschau)?\.pdf#i', $singleFile, $versionMatch)) {
                $aFtpBrochures[$versionMatch[1] . $versionMatch[2]] = $singleFile;
            }
        }
        
        $aData = $sExcel->readFile($localXlsFile, true)->getElement(0)->getData();

        foreach ($aFtpBrochures as $number => $name) {
            $aBrochuresToUse[$name] = '';
            foreach ($aData as $singleStore) {
                if ($singleStore['Version'] != $number) {
                    continue;
                }
                foreach ($aApiStores as $singleApiStore) {
                    if (substr($singleApiStore['zipcode'], 0, 4) != substr((string) $singleStore['PLZ'], 0, 4)) {
                        continue;
                    }
                    if ($sAddress->normalizeStreet(preg_replace('#\s+#', '', $singleApiStore['street'])) != $sAddress->normalizeStreet(preg_replace('#\s+#', '', $sAddress->extractAddressPart('street', $singleStore['Lieferstraße'])))) {
                        continue;
                    }
                    if (!preg_match('#' . $singleApiStore['number'] . '#', $aBrochuresToUse[$name])) {
                        if (strlen($aBrochuresToUse[$name])) {
                            $aBrochuresToUse[$name] .= ', ';
                        }
                        $aBrochuresToUse[$name] .= $singleApiStore['number'];
                    }
                }
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aBrochuresToUse as $name => $storeNumbers) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($name)
                    ->setTitle('Getränke Angebote')
                    ->setStart(date('d.m.Y', strtotime('monday next week')))
                    ->setEnd(date('d.m.Y', strtotime('saturday next week')))
                    ->setVisibleStart(date('d.m.Y', strtotime('sunday')))
                    ->setStoreNumber($storeNumbers)
                    ->setTags('Bier, Limonade, Wasser, Saft, Alkohol, Wein, Pfand, Kasten, Sekt, Schorle')
                    ->setVariety('leaflet');

            if (preg_match('#Vorschau#', $name)) {
                $eBrochure->setTitle('Getränke Angebote (Vorschau)')
                        ->setStart(date('d.m.Y', strtotime('last monday +2 week')))
                        ->setEnd(date('d.m.Y', strtotime('saturday +2 week')))
                        ->setVisibleStart(date('d.m.Y', strtotime('sunday next week')));
            }

            $cBrochures->addElement($eBrochure);
        }

        $sFtp->transformCollection($cBrochures, '/' . $companyId . '/', 'brochures', $localFolder);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
