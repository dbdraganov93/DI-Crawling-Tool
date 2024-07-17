<?php

class Crawler_Company_Mediamarkt_BrochureMail extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sArchive = new Marktjagd_Service_Input_Archive();

        $scriptPath = APPLICATION_PATH . '/../scripts/mailread.php';
        $localDirectory = $sFtp->generateLocalDownloadFolder($companyId);

        $sFtp->connect($companyId);

        exec('php -d mbstring.func_overload=0 ' . $scriptPath . ' ' . $companyId . ' MediaMarktTest');
        $cMails = unserialize(file_get_contents(APPLICATION_PATH . '/../public/files/mail/' . $companyId . '/CollectionData.txt'));

        if (!count($cMails))
        {
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            return $this->_response;
        }

        foreach ($cMails as $eMail)
        {
            foreach ($eMail->getLocalAttachmentPath() as $keyAttachment => $singleAttachmentPath)
            {
                $pattern = '#(\.zip)$#';
                if (preg_match($pattern, $keyAttachment))
                {
                    $eMail = $this->unzipAttachment($eMail, $keyAttachment);
                }
            }
        }

        foreach ($cMails as $eMail)
        {
            $singlePdfPages = array();
            foreach ($eMail->getLocalAttachmentPath() as $keyAttachment => $singleAttachmentPath)
            {
                if (preg_match('#\.zip$#', $singleAttachmentPath))
                {
                    continue;
                }

                $singlePdfPages[] = $singleAttachmentPath;
                sort($singlePdfPages);
            }


            Zend_Debug::dump($singlePdfPages);

            $url = $sPdf->merge($singlePdfPages, $localDirectory);

            $sFtp->upload($url, 'Vorbereitet/' . basename($singlePdfPages[0]));
        }

        $aFilesZipped = $sFtp->listFiles('Zipped/', '#\.zip#');

        foreach ($aFilesZipped as $fileZipped) {
            $fileZippedLocal = $sFtp->downloadFtpToDir($fileZipped, $localDirectory);

            $pathToUnzip = $localDirectory . preg_replace('#.zip#', '', basename($fileZippedLocal));
            $sArchive->unzip($fileZippedLocal, $pathToUnzip);

            $dirIterator = new RecursiveDirectoryIterator($pathToUnzip);
            $singlePdfPages = array();
            foreach (new RecursiveIteratorIterator($dirIterator) as $file) {
                if (preg_match('#__MACOSX#', $file)) {
                    continue;
                }

                if (preg_match('#\.pdf$#is', $file)) {
                    $singlePdfPages[] = (string) $file;
                }
            }

            sort($singlePdfPages);
            Zend_Debug::dump($singlePdfPages);
            $url = $sPdf->merge($singlePdfPages, $localDirectory);
            $sFtp->upload($url, 'Vorbereitet/' . basename($singlePdfPages[0]));

        }

        $sFtp->close();

        $this->_response->setIsImport(false);
        $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
        return $this->_response;
    }

    /**
     * Funktion, um Anhänge zu entpacken und die Pfade in den Email-Entität zu speichern
     * 
     * @param Marktjagd_Entity_Email $eMail
     * @param string $keyAttachment
     * @return Marktjagd_Entity_Email $eMail
     */
    public function unzipAttachment($eMail, $keyAttachment)
    {
        $sArchive = new Marktjagd_Service_Input_Archive();
        $aAttachments = $eMail->getLocalAttachmentPath();
        $unzipPath = preg_replace('#' . $keyAttachment . '#', 'unzipped/', $aAttachments[$keyAttachment]);
        $sArchive->unzip($aAttachments[$keyAttachment], $unzipPath);
        $dirHandle = opendir($unzipPath);
        while (($readDir = readdir($dirHandle)) != FALSE)
        {
            $fileNamePattern = '#^[^\.]#';
            if (!preg_match($fileNamePattern, $readDir))
            {
                continue;
            }
            $eMail->setLocalAttachmentPath($readDir, $unzipPath . $readDir);
        }
        return $eMail;
    }

}
