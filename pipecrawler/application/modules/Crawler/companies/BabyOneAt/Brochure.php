<?php

require APPLICATION_PATH . '/modules/Crawler/companies/BabyOneAt/DynBrochure.php';

/**
 * Brochure Crawler für BabyOne AT (ID: 73170)
 */
class Crawler_Company_BabyOneAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFTP = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sGSheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $aBrochures = $this->getBrochuresFromPlan();

        # no new brochures -> we're done here
        if(empty($aBrochures)) {
            $this->_logger->info('no brochures need to be imported');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            return $this->_response;
        }


        $localFolder = $sFTP->connect('28698', TRUE);
        $sFTP->changedir('Flyer AT');
        foreach($sFTP->listFiles('.', '#\.#') as $singleFile) {
            if(isset($aBrochures[$singleFile])) {
                $this->_logger->info('found brochure to import - ' . $singleFile);
                $aBrochures[$singleFile]['url'] = $sFTP->downloadFtpToDir($singleFile, $localFolder);
            }
            # article file is only necessary for dyn. brochure and discover
            elseif (preg_match('#artikel.*.xlsx$#i', $singleFile)) {
                $localArticleFile = $sFTP->downloadFtpToDir($singleFile, $localFolder);
                $remoteArticleFile = $singleFile;
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

            # if it is a dynamic flyer, we create it
            if(preg_match('#dynamisch#i',$singleBrochure['brochureNr'])) {
                $dynBrochure = new Crawler_Company_BabyOneAt_DynBrochure();
                $singleBrochure['url'] = $dynBrochure->buildDynBrochure($companyId, $remoteArticleFile, $singleBrochure['PDF file']);
                unset($dynBrochure);
            }

            $singleBrochure = $this->handleClickouts($sPdf, $singleBrochure, $companyId);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            if (!preg_match('#BabyOne#', $singleBrochure['title'])) {
                $brochureTitle = 'BabyOne: ' . $singleBrochure['title'];
            } else {
                $brochureTitle = $singleBrochure['title'];
            }

            $eBrochure->setTitle($brochureTitle)
                ->setBrochureNumber(substr($singleBrochure['brochureNr'],0, 32))
                ->setUrl($singleBrochure['url'])
                ->setVariety('leaflet')
                ->setVisibleStart(date('d.m.Y', strtotime($singleBrochure['start'])))
                ->setStart(date('d.m.Y', strtotime($singleBrochure['start'])))
                ->setEnd(date('d.m.Y H:i:s', strtotime($singleBrochure['end'])));


            $cBrochures->addElement($eBrochure);

            # move the PDF file after the creation of the brochure element
            $sFTP->move('./' . $pdfFile, './0_Archiv/' . $pdfFile);
            if($remoteArticleFile)
                $sFTP->move('./' . $remoteArticleFile, './0_Archiv/' . $remoteArticleFile);

        }

        if(count($cBrochures->getElements()) == 0) {
            $this->_logger->info('no brochures have been imported');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            return $this->_response;
        }

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

    /**
     * @param string $gSheetId
     * @return array
     * @throws Exception
     */
    private function getBrochuresFromPlan(): array
    {

        $gSheetId = '1gtNocU-e2-i1uBNu0CMuyTnbXbyydMoqLPszFNfc_R0';
        $aBrochures = [];

        # read all planned brochures from GoogleSheet
        $sGSheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $brochurePlan = $sGSheet->getFormattedInfos($gSheetId, 'A1', 'I', 'geplant');
        $today = time();

        foreach ($brochurePlan as $singleRow) {
            # handle year in 2-digit format
            if (preg_match('#(\d){2}.(\d){2}.(\d){2}$#', $singleRow['Startdatum'])) {
                $singleRow['Startdatum'] = substr($singleRow['Startdatum'], 0, 6) . '20' . substr($singleRow['Startdatum'], 6, 2);
            }
            if (preg_match('#(\d){2}.(\d){2}.(\d){2}$#', $singleRow['Enddatum'])) {
                $singleRow['Enddatum'] = substr($singleRow['Enddatum'], 0, 6) . '20' . substr($singleRow['Enddatum'], 6, 2);
            }

            if (!empty($singleRow['PDF Datei']) && $singleRow['AT'] == 'ja' && strtotime($singleRow['Enddatum'] . ' 23:59:59') >= $today) {
                $aBrochures[trim($singleRow['PDF Datei'])] = [
                    'PDF file' => trim($singleRow['PDF Datei']),
                    'start' => $singleRow['Startdatum'],
                    'end' => $singleRow['Enddatum']  . ' 23:59:59',
                    'title' => $singleRow['Anzeigename'],
                    'brochureNr' => $singleRow['Kampagne'],
                    'tracking' => $singleRow['Link AT']
                ];
            }
        }

        return $aBrochures;
    }

    /**
     * @param Marktjagd_Service_Output_Pdf $sPdf
     * @param $singleBrochure
     * @param int $companyId
     * @return mixed
     * @throws Exception
     */
    private function handleClickouts(Marktjagd_Service_Output_Pdf $sPdf, $singleBrochure, int $companyId)
    {
        # if there are clickout links as annotations, we exchange them with real links
        $singleBrochure['url'] = $sPdf->exchange($singleBrochure['url']);

        # we preserve existing links
        $annotations = $sPdf->getAnnotationInfos($singleBrochure['url']);
        $aData = [];

        $utmParameter = '';

        # set additional link on first page, if there is one configured
        if (strpos($singleBrochure['tracking'], 'https') !== FALSE) {
            $aData[] = [
                'page' => 0,
                'link' => $singleBrochure['tracking'],
                'startX' => '340',
                'endX' => '390',
                'startY' => '740',
                'endY' => '790'
            ];

            $utmParameter = parse_url($singleBrochure['tracking'], PHP_URL_QUERY);
        }

        foreach ($annotations as $key => $annotation) {
            if ($annotation->subtype != 'Link' || $annotation->url == NULL) {
                unset($annotation);
                continue;
            }
            $aData[] = [
                'page' => $annotation->page,
                'link' => $annotation->url . '?' . $utmParameter,
                'startX' => $annotation->rectangle->startX,
                'endX' => $annotation->rectangle->endX,
                'startY' => $annotation->rectangle->startY,
                'endY' => $annotation->rectangle->endY
            ];
        }

        $coordFileName = APPLICATION_PATH . '/../public/files/coordinates_' . $companyId . '.json';

        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aData));
        fclose($fh);

        $singleBrochure['url'] = $sPdf->setAnnotations($singleBrochure['url'], $coordFileName);
        $this->_logger->info('added clickout link to ' . $singleBrochure['url']);
        return $singleBrochure;
    }
}