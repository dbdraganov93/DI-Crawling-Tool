<?php

class Crawler_Company_Mediamarkt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sUnvStores = new Marktjagd_Service_Input_MarktjagdApi();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $sFtp->connect($companyId);
        $localFilePath = $sFtp->downloadFtpToCompanyDir('Kennzeichnung_PLZ.xls', $companyId);

        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $xlsLines = $sExcel->readFile($localFilePath, true)->getElement(0)->getData();
        $cUnvStores = $sUnvStores->findStoresByCompany($companyId);
        $cUnvStores = $cUnvStores->getElements();

        $mappingAr = array();
        // Kennzeichen zu Standortnummern mappen
        foreach ($xlsLines as $line) {
            $plzAr = explode(';', $line['PLZ']);
            $storeAr = array();
            foreach ($plzAr as $plz){
                /** @var Marktjagd_Entity_Api_Store $eStore */
                foreach ($cUnvStores as $eStore) {
                    if ($eStore->getZipcode() == $plz) {
                        $storeAr[] = $eStore->getStoreNumber();
                    }
                }
            }

            // Wenn keine Standort gefunden
            if (count($storeAr) == 0) {
                $mappingAr[$line['Kennzeichen']] = false;
            } else {
                $mappingAr[$line['Kennzeichen']] = implode(',', $storeAr);
            }
        }

        $aFiles = $sFtp->listFiles();

        foreach ($aFiles as $ftpFile){
            if (preg_match('#^D_MM_([^_]+)_([0-9]{2})([0-9]{2})([^_]*)_.+\.pdf#', $ftpFile, $matchAr)){
                // Startdatum erzeugen
                $startDate = mktime(0,0,0,$matchAr[3], $matchAr[2], date("Y"));

                // falls Datum mehr als 30 Tage in der Vergangenheit liegt, dann für nächstes Jahr
                if (time() - $startDate >= 2592000){
                    $startDate = mktime(0,0,0,$matchAr[3], $matchAr[2], date("Y")+1);
                }

                $endDate = strtotime("+2 weekdays", $startDate);

                // Differenz ermitteln
                $dateDiff = gregoriantojd(
                        date('m', $endDate),
                        date('d', $endDate),
                        date('Y', $endDate))
                    - gregoriantojd(
                        date('m', $startDate),
                        date('d', $startDate),
                        date('Y', $startDate));

                // Samstag und Sontag wurde "übersprungen", 2 Tage zurück
                if ($dateDiff == 4){
                    $endDate = strtotime("-2 days", $endDate);
                }

                // Sonntag soll übersprungen werden, weiter auf Montag
                if (date('w', $endDate) == 0){
                    $endDate = strtotime("+1 days", $endDate);
                }

                // Ausnahme Köln, immer sichtbar bis nächsten Samstag
                if (preg_match('#^(K_|K-|K2_|K3_)#',$matchAr[1] . '_')){
                    $endDate = strtotime("next saturday", $startDate) + 72000; //nächsten Samstag 20 Uhr
                    // Ausnahme Verbund Stuttgart, immer sichtbar bis nächsten Montag
                } elseif (preg_match('#^(SFB_|SFB-)#',$matchAr[1] . '_')){
                    $endDate = strtotime("next monday", $startDate) + 86399;
                    // Ausnahme Verbund München, immer 6 Tage sichtbar
                } elseif (preg_match('#^(M4-erdi-karl)#',$matchAr[1] . '_')){
                    $endDate = strtotime("+6 days", $startDate) + 86399;
                    // Anzeigen Memmingen Mi bis Di
                } elseif (preg_match('#^(MEM)#',$matchAr[1] . '_') && date('w', $startDate) == 3){
                    $endDate = strtotime("next tuesday", $startDate) + 86399;
                    // Anzeigen Memmingen Sa bis Mi
                } elseif (preg_match('#^(MEM)#',$matchAr[1] . '_') && date('w', $startDate) == 6){
                    $endDate = strtotime("next wednesday", $startDate) + 86399;

                } else {
                    $endDate += 86399; //bis Enddatum 23.59
                }

                if (!array_key_exists($matchAr[1], $mappingAr)){
                    $this->_logger->err('cannot find sign for file ' . $ftpFile);
                    continue;
                }

                if (!$mappingAr[$matchAr[1]]){
                    $this->_logger->err('cannot find store for file ' . $ftpFile . ', sign ' . $matchAr[1]);
                    continue;
                }

                $visibleStartDate = $startDate;

                // Anzeige der Prospekte in Würzburg bereits 4 Stunden eher (Vortag 20.00 Uhr)
                if (preg_match('#^(WU|WU2|WU3)#',$matchAr[1])){
                    $visibleStartDate = $visibleStartDate - 14400;
                }

                // Anzeige der Prospekte Karlsruhe (KA*) bereits einen Tag eher
                if (preg_match('#^KA#',$matchAr[1])){
                    $visibleStartDate = strtotime("-1 days", $visibleStartDate);
                }

                $localPdfFile = $sFtp->downloadFtpToCompanyDir($ftpFile, $companyId);

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setStoreNumber($mappingAr[$matchAr[1]]);
                $eBrochure->setBrochureNumber(substr($matchAr[1] . '_' . $matchAr[2] . $matchAr[3] . $matchAr[4], 0, 25));

                $eBrochure->setTitle('Technik Angebote');
                $eBrochure->setStart(date("d.m.Y", $startDate));
                $eBrochure->setEnd(date("d.m.Y H:i",   $endDate));
                $eBrochure->setVisibleStart(date("d.m.Y H:i", $visibleStartDate));
                $eBrochure->setVisibleEnd(date("d.m.Y H:i",   $endDate));
                $eBrochure->setVariety('leaflet');
                $eBrochure->setUrl($sFtp->generatePublicFtpUrl($localPdfFile));

                $cBrochures->addElement($eBrochure);
            } elseif (preg_match('#\.pdf#', $ftpFile, $matchAr)){
                $this->_logger->err('invalid filename (wrong schema) for pdf: ' . $ftpFile);
            }
        }

        if (!count($cBrochures->getElements())) {
            $this->_response->setIsImport(FALSE)
                            ->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            return $this->_response;
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
