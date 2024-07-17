<?php

/**
 * Brochure Crawler für Opel (ID: 68847)
 */
class Crawler_Company_Opel_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $cStores = $sApi->findStoresByCompany($companyId);

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $pattern = '#Links\.csv$#';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match($pattern, $singleFile)) {
                $localUrlFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $aData = $sExcel->readFile($localUrlFile, TRUE, ';')->getElement(0)->getData();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($aData as $singleData) {
            foreach ($cStores->getElements() as $eStore) {
                if (preg_match('#' . $singleData['Version - Name'] . '#', $eStore->getStoreNumber())) {
                    $zipcode = $eStore->getZipcode();
                }
            }
            $pattern = '#\/([^\/]+?)$#';
            if (!preg_match($pattern, $singleData['HTML5 - Link - Hompage'], $fileNameMatch)) {
                $this->_logger->info($companyId . ': unable to get file name: ' . $singleData['HTML5 - Link - Hompage']);
                continue;
            }

            $sHttp->getRemoteFile(preg_replace('#(\d)\.(\d)#', '$1$2', $singleData['HTML5 - Link - Hompage']) . '/ww.pdf', $localPath);
            exec('mv ' . $localPath . 'ww.pdf ' . $localPath . $fileNameMatch[1] . '.pdf');
            $localBrochurePath = $localPath . $fileNameMatch[1] . '.pdf';

            $aCoordsToLink = array(
                array(
                    'page' => 1,
                    'height' => 588.3431,
                    'width' => 415.72308,
                    'startX' => 152.358,
                    'endX' => 171.669,
                    'startY' => 115.66,
                    'endY' => 146.558,
                    'link' => 'http://www.opel-fahren.in/D' . $zipcode
                ),
                array(
                    'page' => 4,
                    'height' => 588.3431,
                    'width' => 415.72308,
                    'startX' => 70.9289,
                    'endX' => 90.2402,
                    'startY' => 162.972,
                    'endY' => 193.87,
                    'link' => 'http://www.opel-fahren.in/D' . $zipcode
                ),
                array(
                    'page' => 7,
                    'height' => 588.3431,
                    'width' => 415.72308,
                    'startX' => 220.82,
                    'endX' => 240.132,
                    'startY' => 47.8413,
                    'endY' => 78.7393,
                    'link' => 'http://www.opel-fahren.in/D' . $zipcode
                ),
            );

            $coordFileName = $localPath . 'coordinates_' . $companyId . '_' . $fileNameMatch[1] . '.json';

            $fh = fopen($coordFileName, 'w+');
            fwrite($fh, json_encode($aCoordsToLink));
            fclose($fh);

            $localBrochurePath = $sPdf->setAnnotations($localBrochurePath, $coordFileName);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($sFtp->generatePublicFtpUrl($localBrochurePath))
                    ->setTitle('OPEL NEWS')
                    ->setBrochureNumber($fileNameMatch[1])
                    ->setStoreNumber($singleData['Version - Name'])
                    ->setStart(date('d.m.Y', strtotime('this friday')))
                    ->setEnd(date('d.m.Y', strtotime('saturday next week')))
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet')
                    ->setTrackingBug('');

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
