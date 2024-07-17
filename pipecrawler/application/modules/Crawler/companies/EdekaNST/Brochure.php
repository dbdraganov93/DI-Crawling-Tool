<?php

/*
 * Store Crawler für EDEKA Nordbayern, Sachsen, Thüringen (IDs: 69469 - 69474 - 73726)
 */

class Crawler_Company_EdekaNST_Brochure extends Crawler_Generic_Company
{

    protected $_week;

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_Ftp();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $title = $sApi->findCompanyByCompanyId($companyId)["title"];
        $cStores = $sApi->findStoresByCompany($companyId)->getElements();
        foreach ($cStores as $eStore) {
            $aValidStoreNumbers[] = $eStore->getStoreNumber();
        }

        $this->_week = 'next';
        $kwNr = $sTimes->getWeekNr($this->_week);

        if ($companyId == 73726 && (int)$kwNr % 4 != 3) {
            $sApi = new Marktjagd_Service_Input_MarktjagdApi();
            $activeBrochures = $sApi->findActiveBrochuresByCompany($companyId);
            $activeBrochureFoundForThisWeek = false;

            foreach ($activeBrochures as $activeBrochure) {
                if (preg_match('#KW' . $kwNr . '#', $activeBrochure['brochureNumber'])) {
                    $activeBrochureFoundForThisWeek = true;
                    break;
                }
            }

            if (count($activeBrochures) && $activeBrochureFoundForThisWeek) {
                $this->_response->setLoggingCode(4)
                    ->setFileName(NULL);
                $this->_logger->warn('Active brochures already found for this week... skipping');

                return $this->_response;
            }
        }
        $sEmail = new Marktjagd_Service_Transfer_Email('EdekaNST');

        $cEmails = $sEmail->generateEmailCollection(69470);
        foreach ($cEmails->getElements() as $eEmail) {
            if (!preg_match('#KW\s*' . $kwNr . '#', $eEmail->getSubject())) {
                continue;
            }

            foreach ($eEmail->getLocalAttachmentPath() as $singleAttachment) {
                if (preg_match('#Onlineverteilung_KW' . $kwNr . '#', $singleAttachment, $waMatch)) {
                    $localAssignmentFile = $singleAttachment;
                    break 2;
                }
            }
        }

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $aAssignment = [];
        $aSpecialAssignment = [];

        $aData = $sPss->readFile($localAssignmentFile)->getElement(0)->getData();

        foreach ($aData as $singleRow) {
            if (!preg_match('#\d+#', $singleRow[1])) {
                continue;
            }

            $rowToCheck = $singleRow[1];
            if ($companyId == '69474') {
                $rowToCheck = $singleRow[2];
            }

            if (!in_array($rowToCheck, $aValidStoreNumbers)) {
                continue;
            }

            $singleRow[7] = preg_replace('#\s+#', '', $singleRow[7]);
            if (!array_key_exists($singleRow[7], $aAssignment)) {
                $aAssignment[strtolower(preg_match('#(\.pdf)$#', $singleRow[7]) ? $singleRow[7] : $singleRow[7] . '.pdf')] = [$rowToCheck];
            } elseif (!in_array($rowToCheck, $aAssignment[$singleRow[7]])) {
                $aAssignment[strtolower(preg_match('#(\.pdf)$#', $singleRow[7]) ? $singleRow[7] : $singleRow[7] . '.pdf')][] = $rowToCheck;
            }

            if (strlen($singleRow[10])) {
                $singleRow[10] = preg_replace('#\s+#', '', $singleRow[10]);
                $aSpecialAssignment[strtolower(preg_match('#(\.pdf)$#', $singleRow[10]) ? $singleRow[10] : $singleRow[10] . '.pdf')][] = $rowToCheck;
            }

        }
        foreach ($aAssignment as $key => $value) {
            if (!$value) {
                unset($aAssignment[$key]);
            }
        }

        $aEdekaConfig = [
            'hostname' => 'ftp://transfer.nb.edeka.de',
            'username' => 'Marktjagd_Offerista',
            'password' => 'hWY7bg8txV'
        ];

        $sFtp->connect($aEdekaConfig);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $aFilesToAssign = [];

        foreach ($sFtp->listFiles('./HZ Internet PDFs') as $singleFolder) {
            foreach ($sFtp->listFiles($singleFolder) as $singleSubFolder) {
                $pattern = '#KW\s*' . $kwNr . '#i';
                if (!preg_match($pattern, $singleSubFolder)) {
                    continue;
                }
                foreach ($sFtp->listFiles($singleSubFolder) as $singleFile) {
                    if ($companyId == 73726 && preg_match('#Naturkind#', $singleFolder)) {
                        $aFilesToAssign[basename($singleFile)] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                        break 2;
                    }

                    if (array_key_exists(strtolower(basename($singleFile)), $aAssignment)) {
                        $this->_logger->info($companyId . ': downloading ' . basename($singleFile) . ' to ' . $localPath);
                        $aFilesToAssign[strtolower(basename($singleFile))] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                    }
                }
            }
        }

        if (count($aSpecialAssignment)) {
            foreach ($sFtp->listFiles('./Sonderwerbemittel/KW' . $kwNr) as $singleFile) {
                if (array_key_exists(basename($singleFile), $aSpecialAssignment)) {
                    $this->_logger->info($companyId . ': downloading ' . basename($singleFile) . ' to ' . $localPath);
                    $aSpecialFilesToAssign[strtolower(basename($singleFile))] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                }
            }
        }

        $sFtp->close();

        ksort($aFilesToAssign);
        ksort($aSpecialFilesToAssign);

        if (empty($aFilesToAssign)) {
            $this->_logger->warn('No folder or PDF found in Edeka FTP for KW' . $kwNr);
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aFilesToAssign as $distName => $filePath) {
            $pattern = '#_(\d{2})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})#';
            if (!preg_match($pattern, $filePath, $validityMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure validity for ' . $filePath);
                continue;
            }
            $visibleStart = $validityMatch[1] . '.' . $validityMatch[2] . '.20' . $validityMatch[3];
            $end = $validityMatch[4] . '.' . $validityMatch[5] . '.20' . $validityMatch[6];

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($title . ': Wochenangebote')
                ->setUrl($filePath)
                ->setVisibleStart($visibleStart)
                ->setEnd($end)
                ->setStart(date('d.m.Y', strtotime($eBrochure->getVisibleStart() . ' +1 day')))
                ->setVariety('leaflet')
                ->setOptions('no_cut')
                ->setStoreNumber(implode(',', $aAssignment[$distName]))
                ->setBrochureNumber('KW' . $kwNr . '_' . substr(sha1_file($filePath), 0, 20));

            if ($companyId == 73726) {
                $eBrochure->setDistribution(NULL)
                    ->setStart($validityMatch[1] . '.' . $validityMatch[2] . '.20' . $validityMatch[3])
                    ->setEnd($validityMatch[4] . '.' . $validityMatch[5] . '.20' . $validityMatch[6])
                    ->setVisibleStart($eBrochure->getStart());

                $cBrochures->addElement($eBrochure);
                continue;
            }

            $cBrochures->addElement($eBrochure);
        }

        if (count($aSpecialAssignment)) {
            foreach ($aSpecialFilesToAssign as $fileName => $filePath) {
                $pattern = '#_(\d{2})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})#';
                if (!preg_match($pattern, $filePath, $validityMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure validity for ' . $filePath);
                    continue;
                }
                $visibleStart = $validityMatch[1] . '.' . $validityMatch[2] . '.20' . $validityMatch[3];
                $end = $validityMatch[4] . '.' . $validityMatch[5] . '.20' . $validityMatch[6];

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setTitle($title . ': Wochen Spezial')
                    ->setUrl($filePath)
                    ->setVisibleStart($visibleStart)
                    ->setEnd($end)
                    ->setStart(date('d.m.Y', strtotime($eBrochure->getVisibleStart() . ' +1 day')))
                    ->setVariety('leaflet')
                    ->setOptions('no_cut')
                    ->setStoreNumber(implode(',', $aSpecialAssignment[$fileName]))
                    ->setBrochureNumber('KW' . $kwNr . '_' . substr(sha1_file($filePath), 0, 20));

                $cBrochures->addElement($eBrochure);
            }
        }
        if (count($cBrochures->getElements()) && $companyId == 69473) {
            $sEmail->archiveMail($eEmail);
        }
        return $this->getResponse($cBrochures, $companyId, 4);
    }

}
