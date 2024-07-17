<?php

/**
 * Prospekt Crawler für Kaiser's Tengelmann (ID: 240)
 */
class Crawler_Company_Kaisers_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sMail = new Marktjagd_Service_Transfer_Email('Kaisers');
        $sArchive = new Marktjagd_Service_Input_Archive();
        $sFtp = new Marktjagd_Service_Transfer_Ftp();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $aBrochures = $sApi->findActiveBrochuresByCompany($companyId);

        $brochureNeeded = TRUE;
        foreach ($aBrochures as $singleBrochure) {
            if (is_array($singleBrochure) && (strtotime('+ 2 days') < strtotime($singleBrochure['validTo'])
                    || date('N', strtotime('+ 2 days')) != 7)) {
                $brochureNeeded = FALSE;
                break;
            }
        }

        $cMails = $sMail->generateEmailCollection($companyId, 'Kaisers');

        if (!count($cMails->getElements())) {
            $this->_response->setIsImport(FALSE);
            if ($brochureNeeded) {
                $this->_response->setLoggingCode(3);
            } else {
                $this->_response->setLoggingCode(4);
            }

            return $this->_response;
        }

        $aDists = array(
            'Muenchen-VM' => 'München VM',
            'Muenchen' => 'München/Oberbayern'
        );

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cMails->getElements() as $eMail) {
            $pattern = '#KW\s+(\d+)\s+(\d+)\s+#';
            if (!preg_match($pattern, $eMail->getSubject(), $validityMatch)) {
                $sMail->archiveMail($eMail);
                continue;
            }

            $pattern = '#ftp://([^:]+?):([^\@]+?)\@([^\/]+?)\/(([^\/\.]+?)\.zip)#s';
            if (!preg_match($pattern, $eMail->getText(), $brochureUrlMatch)) {
                throw new Exception($companyId . ': unable to get zip path.');
            }

            $brochureZipName = preg_replace('#=\n#', '', $brochureUrlMatch[4]);
            $localPath = $sFtp->generateLocalDownloadFolder($companyId);

            $curl = curl_init();
            $file = fopen($localPath . $brochureZipName, 'w');
            curl_setopt($curl, CURLOPT_URL, 'ftp://' . $brochureUrlMatch[3] . '/' . $brochureZipName);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_FILE, $file);
            curl_setopt($curl, CURLOPT_USERPWD, $brochureUrlMatch[1] . ':' . $brochureUrlMatch[2]);
            curl_exec($curl);
            curl_close($curl);
            fclose($file);

            foreach (scandir($localPath) as $singleFile) {
                if (preg_match('#\.zip$#', $singleFile)) {
                    if ($sArchive->unzip($localPath . $singleFile, $localPath)) {
                        $unzippedFolder = $localPath . preg_replace('#=\n#', '', $brochureUrlMatch[5]);
                    }
                    break;
                }
            }

            foreach (scandir($unzippedFolder) as $singleLocalFile) {
                $pattern = '#(Muenchen(-VM)?)_[^\.]+?\.pdf$#';
                if (!preg_match($pattern, $singleLocalFile, $assignmentMatch)) {
                    continue;
                }

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setTitle('Wochen Angebote')
                        ->setUrl($sFtp->generatePublicFtpUrl($unzippedFolder . '/' . $singleLocalFile))
                        ->setStart($sTimes->findDateForWeekday($validityMatch[2], $validityMatch[1], 'Mo'))
                        ->setEnd($sTimes->findDateForWeekday($validityMatch[2], $validityMatch[1], 'Sa'))
                        ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 2 days')))
                        ->setDistribution($aDists[$assignmentMatch[1]])
                        ->setVariety('leaflet');

                $cBrochures->addElement($eBrochure);
            }
        }

        if (count($cBrochures->getElements()) == 2) {
            $sMail->archiveMail($eMail);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
