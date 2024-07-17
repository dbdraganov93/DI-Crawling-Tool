<?php

/*
 * Brochure Crawler für GoodYear (ID: 71809)
 */

class Crawler_Company_Goodyear_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sAddress = new Marktjagd_Service_Text_Address();
        $keyFile = '~/.ssh/pdftron_private_key';

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();
        
        $sFtp->connect($companyId);

        $localFolder = $sFtp->generateLocalDownloadFolder($companyId);

        foreach ($sFtp->listFiles() as $singleFile) {
            $pattern = '#teilnehmer\.xls#i';
            if (preg_match($pattern, $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }

            $pattern = '#(GY(_Reiff)?\.pdf)#';
            if (preg_match($pattern, $singleFile, $fileNameMatch)) {
                $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }

            $pattern = '#([^\.]+?)\.jpg#';
            if (preg_match($pattern, $singleFile, $imageNameMatch)) {
                $localImageFiles[$imageNameMatch[1]] = $sFtp->downloadFtpToDir($singleFile, $localFolder);
                $aLocalImageSize[$imageNameMatch[1]] = getimagesize($localImageFiles[$imageNameMatch[1]]);
            }
        }

        foreach (scandir($localFolder) as $singleFile) {
            if (!preg_match('#^\.#', $singleFile)) {
                exec('scp -v -P 2210 -o LogLevel=QUIET -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -r -i '
                        . $keyFile . ' ' . $localFolder . $singleFile // lokaler Pfad csv
                        . ' pdftron@service:/tmp');
            }
        }

        $storeDataSheet = $sExcel->readFile($localStoreFile)->getElement(0);

        $aHeader = array();
        foreach ($storeDataSheet->getData() as $singleEntry) {
            if (!strlen($singleEntry[7])) {
                continue;
            }
            if (!count($aHeader)) {
                $aHeader = $singleEntry;
                continue;
            }
            $aData[] = array_combine($aHeader, $singleEntry);
        }
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aData as $singleStoreData) {
            $reiff = FALSE;
            $localBrochureFileName = 'GY.pdf';
            $localBrochureFilePath = $localFolder . 'GY.pdf';
            if (preg_match('#Reiff#', $singleStoreData['Channel'])) {
                $reiff = TRUE;
                $localBrochureFileName = 'GY_Reiff.pdf';
                $localBrochureFilePath = $localFolder . 'GY_Reiff.pdf';
            }
            
            $strId = $singleStoreData['2_PAYER ID'] . $singleStoreData['PLZ'];
            
            $localBrochureNameWritten = preg_replace('#\.pdf#', '_' . $strId . '.pdf', $localBrochureFileName);
            $localBrochureNameLinked = preg_replace('#\.pdf#', '_linked.pdf', $localBrochureNameWritten);

            $pattern = '#([A-Z][a-z]+\s*(-\s*[A-Z][a-z]+\s*)?\d{1,2}.+?\d{2}\s*-\s*\d{1,2}.+?\d{2}(\s+\d{1,2}.+?\d{2}\s*-\s*\d{1,2}.+?\d{2})?)#';
            if (!preg_match_all($pattern, $singleStoreData['Öffnungszeiten'], $storeHoursMatches)) {
                $storeHoursMatches = NULL;
                $this->_logger->info($companyId . ': no store hours given.');
            }

            $pattern = '#und\s*nach\s*vereinbarung#i';
            if (!preg_match($pattern, $singleStoreData['Öffnungszeiten'], $storeHoursNoteMatch)) {
                $storeHoursNoteMatch = NULL;
                $this->_logger->info($companyId . ': no store hours notes given.');
            }

            $aDataNeeded = array(
                array(
                    'site' => 2,
                    'startX' => 160.0,
                    'startY' => 385.0,
                    'rotation' => 0.0,
                    'text' => array(
                        array(
                            'content' => trim(preg_replace('#(.+?)\n(.+)#', '$1', $singleStoreData['Firma 2'])),
                            'font' => 'e_helvetica_bold',
                            'fontSize' => 3.5,
                            'fontColor' => '0|85|164'
                        ),
                        array(
                            'content' => $singleStoreData['Straße'],
                            'font' => 'e_helvetica',
                            'fontSize' => 3.5,
                            'fontColor' => '0|85|164'
                        ),
                        array(
                            'content' => str_pad($singleStoreData['PLZ'], 5, '0', STR_PAD_LEFT) . ' ' . $singleStoreData['Stadt'],
                            'font' => 'e_helvetica',
                            'fontSize' => 3.5,
                            'fontColor' => '0|85|164'
                        ),
                        array(
                            'content' => 'Tel. ' . $singleStoreData['Telefonnummer'],
                            'font' => 'e_helvetica',
                            'fontSize' => 3.5,
                            'fontColor' => '0|85|164'
                        )
                    ),
                ),
                array(
                    'site' => 2,
                    'startX' => 450.0 - 3.0 * (float) strlen($singleStoreData['inprint']),
                    'startY' => 20.0,
                    'rotation' => 0.0,
                    'text' => array(
                        array(
                            'content' => $singleStoreData['inprint'],
                            'font' => 'e_helvetica_bold',
                            'fontSize' => 3.0,
                            'fontColor' => '255|255|255'
                        )
                    ),
                ),
                array(
                    'site' => 2,
                    'startX' => 1115.0 - 1.5 * (float) strlen($singleStoreData['inprint']),
                    'startY' => 790.0,
                    'rotation' => 0.0,
                    'text' => array(
                        array(
                            'content' => $singleStoreData['inprint'],
                            'font' => 'e_helvetica',
                            'fontSize' => 1.5,
                            'fontColor' => '255|255|255'
                        )
                    ),
                ),
                array(
                    'site' => 3,
                    'startX' => 50.0,
                    'startY' => 320.0,
                    'rotation' => 0.0,
                    'text' => array(
                        array(
                            'content' => trim(preg_replace('#(.+?)\n(.+)#', '$1', $singleStoreData['Firma 2'])),
                            'font' => 'e_helvetica_bold',
                            'fontSize' => 3.5,
                            'fontColor' => '0|85|164'
                        ),
                        array(
                            'content' => $singleStoreData['Straße'],
                            'font' => 'e_helvetica',
                            'fontSize' => 3.5,
                            'fontColor' => '0|85|164'
                        ),
                        array(
                            'content' => str_pad($singleStoreData['PLZ'], 5, '0', STR_PAD_LEFT) . ' ' . $singleStoreData['Stadt'],
                            'font' => 'e_helvetica',
                            'fontSize' => 3.5,
                            'fontColor' => '0|85|164'
                        ),
                        array(
                            'content' => 'Tel. ' . $singleStoreData['Telefonnummer'],
                            'font' => 'e_helvetica',
                            'fontSize' => 3.5,
                            'fontColor' => '0|85|164'
                        )
                    ),
                ),
                array(
                    'site' => 3,
                    'startX' => 30.0,
                    'startY' => 20.0,
                    'rotation' => 0.0,
                    'text' => array(
                        array(
                            'content' => $singleStoreData['inprint'],
                            'font' => 'e_helvetica_bold',
                            'fontSize' => 3.0,
                            'fontColor' => '255|255|255'
                        )
                    ),
                )
            );

            if (!is_null($storeHoursMatches)) {
                $aDataNeeded[] = array(
                    'site' => 3,
                    'startX' => 330.0,
                    'startY' => 300.0,
                    'rotation' => 0.0,
                    'text' => array(
                        array(
                            'content' => 'Öffnungszeiten:',
                            'font' => 'e_helvetica_bold',
                            'fontSize' => 3.5,
                            'fontColor' => '0|85|164'
                        )
                    )
                );

                foreach ($storeHoursMatches[0] as $singleStoreHour) {
                    $singleStoreHour = preg_replace(array('#([A-Z][a-z]+\s*(-\s*[A-Z][a-z]+\s*)?)(\d{1,2}.+?\d{2}\s*-\s*\d{1,2}.+?\d{2})\n(\d{1,2}.+?\d{2}\s*-\s*\d{1,2}.+?\d{2})#', '#([A-Z][a-z]{1})[a-z]+#', '#\s+-\s+#', '#\n#'), array('$1 $3, $1 $4', '$1', '-', ' '), $singleStoreHour);
                    $aDays = preg_split('#\s*,\s*#', $singleStoreHour);

                    foreach ($aDays as $singleStoreHour) {
                        $aSingleStoreHour = preg_split('#\s+#', $singleStoreHour);
                        $strNewStoreHours = $aSingleStoreHour[0];
                        $finish = 25;
                        if (!preg_match('#[a-z]-[A-Z]#', $singleStoreHour)) {
                            $finish = 27;
                        }
                        for ($i = strlen($singleStoreHour); $i < $finish; $i++) {
                            $strNewStoreHours .= ' ';
                        }
                        $strNewStoreHours .= $aSingleStoreHour[1];

                        $aDataNeeded[count($aDataNeeded) - 1]['text'][] = array(
                            'content' => $strNewStoreHours . ' Uhr',
                            'font' => 'e_helvetica',
                            'fontSize' => 3.5,
                            'fontColor' => '0|85|164'
                        );
                    }
                }

                if (!is_null($storeHoursNoteMatch)) {
                    $aDataNeeded[count($aDataNeeded) - 1]['text'][] = array(
                        'content' => 'und nach Vereinbarung',
                        'font' => 'e_helvetica',
                        'fontSize' => 3.5,
                        'fontColor' => '0|85|164'
                    );
                }
            }
            
            if (!$reiff && !preg_match('#Other#i', $singleStoreData['Channel'])) {
                $aDataNeeded[] = array(
                    'site' => 2,
                    'startX' => 550.0 - (((float) $aLocalImageSize[$singleStoreData['Channel']][0] / (float) $aLocalImageSize[$singleStoreData['Channel']][1]) * 55.0),
                    'startY' => 313.0,
                    'height' => 55.0,
                    'width' => ((float) $aLocalImageSize[$singleStoreData['Channel']][0] / (float) $aLocalImageSize[$singleStoreData['Channel']][1]) * 55.0,
                    'image' => '/tmp/' . preg_replace('#\s+#', '', $singleStoreData['Channel']) . '.jpg'
                );

                $aDataNeeded[] = array(
                    'site' => 3,
                    'startX' => 50.0,
                    'startY' => 353.0,
                    'height' => 55.0,
                    'width' => ((float) $aLocalImageSize[$singleStoreData['Channel']][0] / (float) $aLocalImageSize[$singleStoreData['Channel']][1]) * 55.0,
                    'image' => '/tmp/' . preg_replace('#\s+#', '', $singleStoreData['Channel']) . '.jpg'
                );
            }
            
            $b64Data = base64_encode(json_encode($aDataNeeded));
            
            exec('ssh -p 2210 -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -i '
                    . $keyFile . ' pdftron@service LD_LIBRARY_PATH=. ./addElement.php /tmp/'
                    . $localBrochureFileName . ' /tmp/' . $localBrochureNameWritten . ' \'' . $b64Data . '\'');

            exec('scp -v -P 2210 -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -i '
                    . $keyFile . ' pdftron@service:/tmp/' . $localBrochureNameWritten
                    . ' ' . $localFolder);

            $localLinkPath = $localFolder . 'coordinates.csv';
            $localLinkFileName = 'coordinates.csv';

            $aLinkData = array(
                array(
                    'pageNo',
                    'pageHeight',
                    'pageWidth',
                    'startX',
                    'endX',
                    'startY',
                    'endY',
                    'link'
                ), array(
                    2,
                    841.0,
                    1190.0,
                    305.0,
                    335.0,
                    20.0,
                    40.0,
                    $singleStoreData['url']
                ), array(
                    2,
                    841.0,
                    1190.0,
                    1050.0,
                    1075.0,
                    775.0,
                    800.0,
                    $singleStoreData['url']
                ), array(
                    3,
                    841.0,
                    595.0,
                    300.0,
                    330.0,
                    20.0,
                    40.0,
                    $singleStoreData['url']
            ));

            $fh = fopen($localLinkPath, 'w+');
            foreach ($aLinkData as $singleLinkData) {
                fputcsv($fh, $singleLinkData, ';');
            }
            fclose($fh);
            
            exec('scp -v -P 2210 -o LogLevel=QUIET -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -r -i '
                    . $keyFile . ' ' . $localLinkPath // lokaler Pfad csv
                    . ' pdftron@service:/tmp'); // Remote-Pfad PDF

            exec('ssh -p 2210 -o LogLevel=QUIET -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -i '
                    . $keyFile . ' pdftron@service LD_LIBRARY_PATH=.'
                    . ' ./addLink.php /tmp/' . $localBrochureNameWritten . ' /tmp/' . $localBrochureNameLinked
                    . ' /tmp/' . $localLinkFileName);

            exec('scp -v -P 2210 -o LogLevel=QUIET -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -r -i '
                    . $keyFile // Pfad zum PDFTron Key File
                    . ' pdftron@service:/tmp/' . $localBrochureNameLinked . ' '
                    . $localFolder);
            
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Haben Sie das nötige Profil?')
                    ->setStart($singleStoreData['Start '] . '2016')
                    ->setEnd($singleStoreData['Ende'] . '2016')
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet')
                    ->setUrl(preg_replace('#.*?(/files/(pdf|http|ftp)/.*?)$#', 'https://di-gui.marktjagd.de$1', $localFolder . $localBrochureNameLinked))
                    ->setBrochureNumber($strId)
                    ->setStoreNumber($strId);
                        
            $cBrochures->addElement($eBrochure);
        }
        $sFtp->close();
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
