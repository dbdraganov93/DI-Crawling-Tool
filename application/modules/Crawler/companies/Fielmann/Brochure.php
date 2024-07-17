<?php
/**
 * Discover Crawler für Fielmann (ID: 22387)
 */


class Crawler_Company_Fielmann_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFTP = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sPdf = new Marktjagd_Service_Output_Pdf();


        // -- Change here! -- For now this is manual
        $assignmentFile = 'Datalist.xlsx';
        $brochureTitle = 'Fielmann: Angebote';
        $startDate = '20.04.2022';
        $endDate = '15.05.2022 23:59:59';
        $brochureVariants = [
            '19/69/19' => 'Fielmann_Folder_VarianteA_19_69_19_WEB_1.pdf',
            '16/48/16' => 'Fielmann_Folder_VarianteB_16_48_16_WEB_1.pdf',
        ];

        $localFolder = $sFTP->connect($companyId . '/test', true);

        foreach ($sFTP->listFiles() as $singleFile) {

            if ($singleFile == $brochureVariants['19/69/19']) {
                $this->_logger->info('found brochure to import - ' . $singleFile);
                $brochureVariants['19/69/19'] = $sFTP->downloadFtpToDir($singleFile, $localFolder);
            }
            if ($singleFile == $brochureVariants['16/48/16']) {
                $this->_logger->info('found brochure to import - ' . $singleFile);
                $brochureVariants['16/48/16'] = $sFTP->downloadFtpToDir($singleFile, $localFolder);
            }

            if (preg_match('#' . $assignmentFile . '#', $singleFile)) {
                $this->_logger->info('found Excel to import - ' . $singleFile);
                $assignmentFile = $sFTP->downloadFtpToDir($singleFile, $localFolder);
            }
        }

        $assignmentData = $sExcel->readFile($assignmentFile, true)->getElement(0)->getData();

        foreach ($assignmentData as $assignmentRow) {
            if(empty($assignmentRow['NDL']))
                continue;

            $brochureFile = $this->getBrochureFile($brochureVariants[$assignmentRow['Folder']], $assignmentRow['NDL'], $sPdf, $companyId);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($brochureTitle)
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setVisibleStart($eBrochure->getStart())
                ->setBrochureNumber("FM1_" . $assignmentRow['Folder'] . "_" . $assignmentRow['NDL'])
                ->setVariety('leaflet')
                ->setStoreNumber($assignmentRow['NDL'])
                ->setUrl($brochureFile);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function getBrochureFile($brochureVersion, $storeNumber, Marktjagd_Service_Output_Pdf $sPdf, int $companyId): string
    {
        copy($brochureVersion, APPLICATION_PATH . '/../public/files/tmp/' . $storeNumber . '.pdf');
        $brochureFile = APPLICATION_PATH . '/../public/files/tmp/' . $storeNumber . '.pdf';

        $aData = [];
        $aData[] = [
            'page' => 0,
            'link' => 'https://termine.fielmann.de/?branchId=001-' . $storeNumber . '&productCategory=GL&locale=de-DE&utm_source=offerista&utm_medium=display&utm_campaign=media_display-prospecting_digitale-prospekte_2022-04-11',
            'startX' => '75',
            'endX' => '170',
            'startY' => '22',
            'endY' => '34'
        ];

        $annotations = $sPdf->getAnnotationInfos($brochureFile);
        foreach ($annotations as $annotation) {
            if ($annotation->subtype != 'Link' || $annotation->url == NULL) {
                continue;
            }
            $aData[] = [
                'page' => $annotation->page,
                'link' => $annotation->url,
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
        $brochureFile = $sPdf->setAnnotations($brochureFile, $coordFileName);
        return $brochureFile;
    }
}

