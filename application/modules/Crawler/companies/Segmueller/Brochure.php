<?php

/**
 * Brochure Crawler für Segmüller (ID: 78)
 */
class Crawler_Company_Segmueller_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sExcel = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sMail = new Marktjagd_Service_Transfer_Email('Segmueller');
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $cMails = $sMail->generateEmailCollection($companyId, 'Segmueller');

        foreach ($cMails->getElements() as $eMail) {
            $xlsFileData = $sExcel->readFile(array_values($eMail->getLocalAttachmentPath())[0])->getElement(0)->getData();

            $aHeaders = array();
            $aData = array();
            foreach ($xlsFileData as $singleLine) {
                if (!strlen($singleLine[4])) {
                    continue;
                }

                if (!count($aHeaders)) {
                    $aHeaders = $singleLine;
                    continue;
                }

                $aData[] = array_combine($aHeaders, $singleLine);
            }

            $aBrochureNeeded = array();
            foreach ($aData as $singleLine) {
                foreach ($singleLine as $lineKey => $lineValue) {
                    if (strlen($lineKey) == 2 && preg_match('#x#i', $lineValue)) {
                        $aBrochureNeeded[preg_replace('#\s+([A-Z](_[A-ZÄÖÜ][a-zäöüß]+)?)$#', '$1', $singleLine['Bezeichnung'])][$lineKey] = 'needed';
                    }
                }
            }

            // store dependency
            $storeDeps = array(
                // single store -> single store
                'FD' => array('FD'),
                'WS' => array('WS'),
                'PA' => array('PA'),
                'NB' => array('NB'),
                'MA' => array('MA'),
                'ST' => array('ST'),
                'FM' => array('FM'),
                'PH' => array('PH'),
                'PU' => array('PU'),
                // store group -> single store
                'GF' => array('WS', 'PA', 'PU', 'FD'),
                'FI' => array('FD', 'WS', 'PA', 'NB', 'MA'),
                'MN' => array('NB', 'MA'),
                'FP' => array('FD', 'PA'),
                'FW' => array('FD', 'WS'),
                'PW' => array('PA', 'WS'),
                'WP' => array('PA', 'WS')
            );

            $sFtp->connect($companyId);
            $localDirectory = $sFtp->generateLocalDownloadFolder($companyId);

            $aFolders = $sFtp->listFiles();
            // alle Verzeichnisse finden -> Standortzugehörigkeit und Laufzeit ablesen
            foreach ($aFolders as $singleFolder) {
                if ($singleFolder == 'Archiv'
                    || $singleFolder == 'Auswertung'
                ) {
                    continue;
                }

                if (!preg_match('#(.+?)[\_+|\s+](.+?)[\s|\_]+(\d+\.?\d+)\.?\s*-\s*(\d+\.?\d+)#', $singleFolder, $dirMatch)) {
                    $this->_logger->err('folder does not match: ' . $singleFolder);
                }

                $this->_logger->info('found dir ' . $dirMatch[0]);

                // Laufzeit setzen
                $validFrom = $sTimes->getDateWithAssumedYear(preg_replace('#(\d{2})(\d{2})#', '$1.$2', $dirMatch[3]), 'd.m.Y');
                $validTo = $sTimes->getDateWithAssumedYear(preg_replace('#(\d{2})(\d{2})#', '$1.$2', $dirMatch[4]), 'd.m.Y', $validFrom);
                $visibleFrom = date('d.m.Y', strtotime('-1day', strtotime($validFrom))) . ' 20:00';

                $aFiles = $sFtp->listFiles($singleFolder, '#\.pdf#');

                foreach ($aFiles as $singleFile) {
                    if (preg_match('#\_([A-Z]{2})[\_|\.|\s]#', $singleFile, $matchDist)) {
                        if (!array_key_exists($matchDist[1], $storeDeps)) {
                            $this->_logger->err('distribution ' . $matchDist[1] . ' seems to be no valid distribution');
                            continue;
                        }

                        $localFile = $sFtp->downloadFtpToDir($singleFile, $localDirectory);
                        $localFile = $sPdf->implementSurvey($localFile, 3);
                        $publicFileUrl = $sFtp->generatePublicFtpUrl($localFile);

                        foreach ($storeDeps[$matchDist[1]] as $singleValidStore) {
                            $eBrochure = new Marktjagd_Entity_Api_Brochure();
                            foreach ($aData as $singleLine) {
                                $aFileName = explode(' ', $singleLine['Bezeichnung']);
                                if (preg_match('#' . $aFileName[0] . '#', $dirMatch[1])) {
                                    $eBrochure->setTitle($singleLine['H1-Titel']);
                                    if (preg_match('#X#', $singleLine[$singleValidStore])) {
                                        unset($aBrochureNeeded[$singleLine['Bezeichnung']][$singleValidStore]);
                                    }
                                }
                            }

                            $storeNumber = $singleValidStore;
                            if (preg_match('#\_([A-Z]{2})\.pdf#i', $localFile, $matchStoreNumber)) {
                                $storeNumber = $matchStoreNumber[1];
                            }

                            $eBrochure->setStart($validFrom)
                                ->setVisibleStart($visibleFrom)
                                ->setEnd($validTo)
                                ->setVisibleEnd($validTo . ' 20:00')
                                ->setStoreNumber($storeNumber)
                                ->setBrochureNumber($dirMatch[1] . '_' . $dirMatch[2] . '_' . $storeNumber)
                                ->setVariety('leaflet')
                                ->setTrackingBug('https://ad.doubleclick.net/ddm/ad/N8758.2580019OFFERISTAGROUPGMBH/'
                                    . 'B11039323.146951907;sz=1x1;ord=%%CACHEBUSTER%%;dc_lat=;dc_rdid=;tag_for_child_directed_treatment=?')
                                ->setUrl($publicFileUrl);

                            if (strlen($eBrochure->getUrl())) {
                                $cBrochures->addElement($eBrochure);
                            }
                        }
                    }
                }
            }

            $complete = TRUE;
            foreach ($aBrochureNeeded as $singleBrochureNeeded) {
                if (count($singleBrochureNeeded)) {
                    $complete = FALSE;
                }
            }

            if ($complete) {
                $sMail->archiveMail($eMail);
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
