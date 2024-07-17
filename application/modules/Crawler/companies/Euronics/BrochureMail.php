<?php

class Crawler_Company_Euronics_BrochureMail extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sMail = new Marktjagd_Service_Transfer_Email('Euronics');
        
        $scriptPath = APPLICATION_PATH . '/../scripts/mailread.php';
        exec('php -d mbstring.func_overload=0 ' . $scriptPath . ' ' . $companyId . ' Euronics');
        $cMails = unserialize(file_get_contents(APPLICATION_PATH . '/../public/files/mail/' . $companyId . '/CollectionData.txt'));
        
        if (count($cMails)) {

            $aForeignNo = array(
                '#510001#',
                '#1101166#'
            );

            $aOwnNo = array(
                '215741',
                '1101167'
            );

            foreach ($cMails as $eMail) {
                foreach ($eMail->getLocalAttachmentPath() as $keyAttachment => $singleAttachmentPath) {
                    $pattern = '#(\.zip)$#';
                    if (preg_match($pattern, $keyAttachment)) {
                        $eMail = $this->unzipAttachment($eMail, $keyAttachment);
                    }
                }
            }

            foreach ($cMails as $eMail) {
                $attachmentNo = 0;
                foreach ($eMail->getLocalAttachmentPath() as $keyAttachment => $singleAttachmentPath) {
                    $eBrochure = new Marktjagd_Entity_Api_Brochure();
                    $pattern = '#(.+?)[-|_]([0-9]{6,8})[-|_]([0-9]{6,8})(\_[0-9]{1})?\s*\.pdf$#i';
                    if (!preg_match($pattern, $keyAttachment, $match)) {
                        $logger->info($companyId . ': there is a non pdf attachment in email "' . $eMail->getSubject() . '"');
                        continue;
                    }
                    $aStoreNumbers = preg_split('#(_|-)#', $match[1]);
                    $strStores = '';
                    foreach ($aStoreNumbers as $singleStoreNumber) {
                        if (strlen($singleStoreNumber) < 4) {
                            continue;
                        }
                        if (strlen($strStores)) {
                            $strStores .= ', ';
                        }
                        $strStores .= preg_replace($aForeignNo, $aOwnNo, $singleStoreNumber);
                    }
                    $eBrochure->setStoreNumber($strStores);
                    
                    if (strlen(trim($match[2])) == 8 && strlen(trim($match[3])) == 8) {
                        $datePattern = '#.*?([0-9]{2})([0-9]{2})([0-9]{4})$#';
                        $dateReplacement = '$1.$2.$3';
                    }
                    if (strlen(trim($match[2])) == 6 && strlen(trim($match[3])) == 6) {
                        $datePattern = '#.*?([0-9]{2})([0-9]{2})([0-9]{2})$#';
                        $dateReplacement = '$1.$2.20$3';
                    }
                    $eBrochure->setStart(preg_replace($datePattern, $dateReplacement, trim($match[2])))
                            ->setEnd(preg_replace($datePattern, $dateReplacement, trim($match[3])))
                            ->setVisibleStart(preg_replace($datePattern, $dateReplacement, trim($match[2])))
                            ->setUrl($eBrochure->generatePublicBrochurePath($singleAttachmentPath))
                            ->setTitle('Technik Angebote')
                            ->setVariety('leaflet');

                    if ($eMail->getFromAddress() == 'assistenzmv@euronics-tut.de') {
                        $eBrochure->setVisibleStart(date('d.m.Y H:i:s', strtotime($eBrochure->getVisibleStart() . '-3hours')));
                    }
                    $cBrochures->addElement($eBrochure);
                    $attachmentNo++;
                }
                if ($attachmentNo > 0) {
                    $sMail->archiveMail($eMail);
                } else {
                    $logger->err($companyId . ': no correct attachment on email "' . $eMail->getSubject() . '".');
                    $sMail->moveMail($eMail, 'INBOX');
                }
            }

            $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
            $fileName = $sCsv->generateCsvByCollection($cBrochures);

            $this->_response->generateResponseByFileName($fileName);
        } else {
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
        }

        return $this->_response;
    }

    /**
     * Funktion, um Anhänge zu entpacken und die Pfade in den Email-Entität zu speichern
     * 
     * @param Marktjagd_Entity_Email $eMail
     * @param string $keyAttachment
     * @return Marktjagd_Entity_Email $eMail
     */
    public function unzipAttachment($eMail, $keyAttachment) {
        $sArchive = new Marktjagd_Service_Input_Archive();
        $aAttachments = $eMail->getLocalAttachmentPath();
        $unzipPath = preg_replace('#' . $keyAttachment . '#', 'unzipped/', $aAttachments[$keyAttachment]);
        $sArchive->unzip($aAttachments[$keyAttachment], $unzipPath);
        $dirHandle = opendir($unzipPath);
        while (($readDir = readdir($dirHandle)) != FALSE) {
            $fileNamePattern = '#^[^\.]#';
            if (!preg_match($fileNamePattern, $readDir)) {
                continue;
            }
            $eMail->setLocalAttachmentPath($readDir, $unzipPath . $readDir);
        }
        return $eMail;
    }

}
