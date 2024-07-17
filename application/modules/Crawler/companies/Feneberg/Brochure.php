<?php
/**
 * Brochure Crawler für Feneberg (ID: 80006)
 */

class Crawler_Company_Feneberg_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $aStoresToAdvertise = [
            'id:1500756',
            'id:1500796',
            'id:1500758',
            'id:1500762',
            'id:1500750',
            'id:1530682',
        ];

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sGSheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $today = time();
        $gSheetId = '136ndav2tEDaLP4ZpkFiL6_BSJ_Xmno2IXVkJgc03Sjg';

        $brochurePlan = $sGSheet->getFormattedInfos($gSheetId, 'A1', 'H', 'marketing plan');

        $aBrochures = [];

        foreach($brochurePlan as $singleRow) {
            if(!empty($singleRow['PDF-Datei']) &&  strtotime($singleRow['gültig bis']) >= $today) {
                $aBrochures[trim($singleRow['PDF-Datei'])] = [
                    'PDF file' => trim($singleRow['PDF-Datei']),
                    'start' => $singleRow['gültig von'],
                    'end' => $singleRow['gültig bis'],
                    'visibleStart' => $singleRow['angezeigt von'],
                    'visibleEnd' => $singleRow['angezeigt bis'],
                    'title' => $singleRow['Anzeigename'],
                    'brochureNr' => $singleRow['Kampagne'],
                    'tracking' => $singleRow['Clickout URL (optional)']
                ];
            }
        }

        if(empty($aBrochures)) {
            $this->_logger->info('no brochures need to be imported');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            return $this->_response;
        }

        $localFolder = $sFtp->connect($companyId, TRUE);
        foreach($sFtp->listFiles('.', '#\.#') as $singleFile) {
            if($aBrochures[$singleFile]) {
                $this->_logger->info('found brochure to import - ' . $singleFile);
                $aBrochures[$singleFile]['url'] = $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }
            else
            {
                $this->_logger->warn('found file that is not in the marketing plan: ' . $singleFile);
            }
        }

        $activeBrochures = $sApi->findActiveBrochuresByCompany($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach($aBrochures as $pdfFile => $singleBrochure) {
            if(empty($singleBrochure['url'])) {
                if($this->searchForBrochure($singleBrochure['brochureNr'], $activeBrochures)) {
                    $this->_logger->info('Brochure ' . $singleBrochure['brochureNr'] . ' already imported.');
                    continue;
                }
                $this->_logger->warn('Missing brochure on the FTP server: ' . $singleBrochure['PDF file']);
                continue;
            }
            # set Clickout-Link, if there is one
            if(strpos($singleBrochure['tracking'],'https') !== FALSE) {
                $aData = array(
                    array(
                        'page' => 0,
                        'link' => $singleBrochure['tracking'],
                        'startX' => '340',
                        'endX' => '390',
                        'startY' => '740',
                        'endY' => '790'
                    )
                );

                $coordFileName = APPLICATION_PATH . '/../public/files/coordinates_' . $companyId . '.json';

                $fh = fopen($coordFileName, 'w+');
                fwrite($fh, json_encode($aData));
                fclose($fh);

                $singleBrochure['url'] = $sPdf->setAnnotations($singleBrochure['url'], $coordFileName);
                $this->_logger->info('added clickout link to ' . $singleBrochure['url']);
            }

            #$singleBrochure['url'] = $sPdf->implementSurvey( $singleBrochure['url'], 3);


            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($singleBrochure['title'])
                ->setBrochureNumber(substr($singleBrochure['brochureNr'] . '_' . $singleBrochure['start'],0, 32))
                ->setUrl($singleBrochure['url'])
                ->setVariety('leaflet')
                ->setVisibleStart(date('d.m.Y', strtotime($singleBrochure['visibleStart'])))
                ->setVisibleEnd(date('d.m.Y', strtotime($singleBrochure['visibleEnd'])))
                ->setStart(date('d.m.Y', strtotime($singleBrochure['start'])))
                ->setEnd(date('d.m.Y', strtotime($singleBrochure['end'])))
                ->setStoreNumber(implode(',', $aStoresToAdvertise));

            $cBrochures->addElement($eBrochure);

            # move the PDF file after the creation of the brochure element
            $sFtp->move('./' . $pdfFile, './0_Archiv/' . $pdfFile);
        }

        if(count($cBrochures->getElements()) == 0) {
            $this->_logger->info('no brochures have been imported');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            return $this->_response;
        }

        $sFtp->close();
        return $this->getResponse($cBrochures, $companyId);
    }

    private function searchForBrochure($id, $array) {
        foreach ($array as $key => $val) {
            if ($val['brochureNumber'] === $id) {
                return $key;
            }
        }
        return null;
    }
}
