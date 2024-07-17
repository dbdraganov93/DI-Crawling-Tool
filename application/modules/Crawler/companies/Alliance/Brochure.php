<?php

/**
 * Brochure Crawler for Alliance Healthcare (ID: 89979)
 *
 * Alliance/Brochure 89979
 */
class Crawler_Company_Alliance_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.pdf#', $singleRemoteFile)) {
                $localBrochure = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                continue;
            }
            if (preg_match('#\.csv#', $singleRemoteFile)) {
                $storeFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        $aImages = [];
        foreach ($sFtp->listFiles('./Apothekenlogos') as $singleLogo) {
            $aImages[basename($singleLogo)] = $sFtp->downloadFtpToDir($singleLogo, $localPath);
        }

        $sFtp->close();

        $width = $sPdf->getAnnotationInfos($localBrochure)[0]->width;
        $height = $sPdf->getAnnotationInfos($localBrochure)[0]->height;

        $aData = $sPss->readFile($storeFile, TRUE, ';')->getElement(0)->getData();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aData as $singleRow) {
            if (copy($localBrochure, $localPath . preg_replace('#\.jpg#', '.pdf', $singleRow['Logodatei']))) {
                $localBrochurePath = $localPath . preg_replace('#\.jpg#', '.pdf', $singleRow['Logodatei']);
            }
            $fileNumber = pathinfo($localBrochurePath, PATHINFO_FILENAME);

            $titleLength = strlen($singleRow['Apothekenbezeichnung']);
            $titleFont = 425 / $titleLength;

            $addressLength = strlen(str_pad($singleRow['PLZ'], 5, '0', STR_PAD_LEFT) . ' ' . $singleRow['Ort']);
            $addressFont = 300 / $addressLength;

            $fontToUse = $titleFont;
            if ($addressFont < $titleFont) {
                $fontToUse = $addressFont;
            }

            $aElementsToSet = [
                [
                    'page' => 0,
                    'startX' => 200,
                    'startY' => 215,
                    'type' => 'text',
                    'contents' => preg_replace('#%#', '', $singleRow['Rabatt']),
                    'font' => ['fontType' => 'Helvetica_Bold', 'fontSize' => 40, 'fontColor' => '255|255|255']
                ],
                [
                    'page' => 0,
                    'startX' => 30,
                    'startY' => 25 + $fontToUse,
                    'type' => 'text',
                    'contents' => $singleRow['Apothekenbezeichnung'],
                    'font' => ['fontType' => 'Helvetica_Bold', 'fontSize' => $fontToUse, 'fontColor' => '255|255|255']
                ],
                [
                    'page' => 0,
                    'startX' => 30,
                    'startY' => 20,
                    'type' => 'text',
                    'contents' => str_pad($singleRow['PLZ'], 5, '0', STR_PAD_LEFT) . ' ' . $singleRow['Ort'],
                    'font' => ['fontType' => 'Helvetica', 'fontSize' => $fontToUse, 'fontColor' => '255|255|255']
                ]
            ];

            if ($aImages[$singleRow['Logodatei']]) {
                $aElementsToSet[] = [
                    'page' => 0,
                    'startX' => $width - 198,
                    'startY' => 10,
                    'endX' => $width - 10,
                    'endY' => 104,
                    'type' => 'image',
                    'path' => $aImages[$singleRow['Logodatei']],
                    'scaling' => TRUE
                ];
            }

            $jsonFilePath = APPLICATION_PATH . '/../public/files/tmp/elementsToSet_' . $fileNumber . '.json';

            $fh = fopen($jsonFilePath, 'w+');
            fwrite($fh, json_encode($aElementsToSet));
            fclose($fh);

            $localBrochurePath = $sPdf->addElements($localBrochurePath, $jsonFilePath);

            $aCoordsToLink = [
                [
                    'page' => 0,
                    'height' => $height,
                    'width' => $width,
                    'startX' => 100,
                    'endX' => 110,
                    'startY' => 150,
                    'endY' => 160,
                    'link' => $singleRow['URL']
                ]
            ];

            $clickoutFile = APPLICATION_PATH . '/../public/files/tmp/clickout_' . $fileNumber . '.json';
            $fh = fopen($clickoutFile, 'w+');
            fwrite($fh, json_encode($aCoordsToLink));
            fclose($fh);

            $localBrochurePath = $sPdf->setAnnotations($localBrochurePath, $clickoutFile);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Gemeinsam mehr bewegen!')
                ->setUrl($localBrochurePath)
                ->setBrochureNumber($fileNumber . date('_m_Y', strtotime($singleRow['active_period_from_date'])))
                ->setStart($singleRow['active_period_from_date'])
                ->setEnd($singleRow['active_period_to_date'])
                ->setVisibleStart($eBrochure->getStart())
                ->setStoreNumber($fileNumber);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }
}
