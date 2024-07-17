<?php

/**
 * Service zum Bearbeiten von PDFs
 */
class Marktjagd_Service_Output_Pdf
{

    /**
     * Fügt den Inhalt der übergebenen PDF-Dateien zu einer neuen PDF-Datei zusammen
     *
     * @param array $aFiles Array mit Pfaden der aneinanderzuhängenden PDF's
     * @param string $outputFolder Pfad in dem das zusammengefügte PDF ausgegeben werden soll
     * @return string Pfad zu dem neuen PDF-File
     * @throws Exception
     */
    public function merge($aFiles, $outputFolder)
    {
        $filePathJoined = $outputFolder . md5(implode(',', $aFiles)) . '.pdf';

        if (count($aFiles) > 1) {
            foreach ($aFiles as &$singleFile) {
                $singleFile = '\'' . $singleFile . '\'';
            }

            exec('java -Djava.awt.headless=true -Xmx2048m -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -j '
                . implode(' ', $aFiles) . ' ' . $filePathJoined, $retValue, $retVar);

            if (count($retValue)) {
                throw new Exception('error while joining pages in folder: ' . $outputFolder . '-' . implode("\n", $retValue));
            }
        } else {
            return $aFiles[0];
        }

        return $filePathJoined;
    }

    /**
     * Entfernt den Druckrand eines PDF-Files
     *
     * @param string $filePath Pfad des zu trimmenden PDF-Files
     * @return string Pfad zu dem neuen PDF-File
     * @throws Exception
     */
    public function trim($filePath)
    {
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -t '
            . escapeshellarg($filePath) . ' ' . escapeshellarg($filePath), $retValue, $retVar);

        if (count($retValue)) {
            throw new Exception('error while trimming brochure: ' . $filePath . '-' . implode("\n", $retValue));
        }

        return $filePath;
    }

    /**
     * Tauscht "Nicht-Links" gegen Links aus und löscht die "Nicht-Links"
     *
     * @param string $filePath
     * @return string
     * @throws Exception
     */
    public function exchange($filePath)
    {
        $filePathExchanged = preg_replace('#\.pdf$#', '_exchanged.pdf', $filePath);
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -r '
            . escapeshellarg($filePath) . ' ' . escapeshellarg($filePathExchanged) . '>>/tmp/foo.log', $retValue, $retVar);

        if (count($retValue)) {
            throw new Exception('error while replacing annotations for brochure: ' . $filePath . '-' . implode("\n", $retValue));
        }

        return $filePathExchanged;
    }

    /**
     * Fügt Links aus json-File in PDF ein
     *
     * @param string $filePath
     * @param string $jsonCoordinatesFile
     * @return string
     * @throws Exception
     */
    public function setAnnotations($filePath, $jsonCoordinatesFile)
    {
        $filePathLinked = preg_replace('#\.pdf#', '_linked.pdf', $filePath);
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -i '
            . escapeshellarg($filePath) . ' ' . escapeshellarg($jsonCoordinatesFile) . ' ' . escapeshellarg($filePathLinked), $retValue, $retVar);

        if (count($retValue)) {
            throw new Exception('error while inserting links to brochure: ' . $filePath . '-' . implode("\n", $retValue));
        }

        return $filePathLinked;
    }

    /**
     * delivers a Json File from a Array
     *
     * @param array $coordinates
     * @return string
     * @throws Zend_Exception
     */
    public function getJsonCoordinatesFile(array $coordinates): string
    {

        foreach ($coordinates as $key => $singleAnnot) {
            // optional => 'width', 'height', 'maxX', 'maxY'
            if (isset($singleAnnot['page'], $singleAnnot['startX'], $singleAnnot['startY'],
                $singleAnnot['endX'], $singleAnnot['endY'], $singleAnnot['link'])) {
                continue;
            }
            unset($coordinates[$key]);
            Zend_Registry::get('logger')->err("$key: Coordinates Entry not valid.");
        }

        $jsonFile = APPLICATION_PATH . '/../public/files/template_' . date('dmYHims') . '.json';
        $fh = fopen($jsonFile, 'w');
        fwrite($fh, json_encode($coordinates));
        fclose($fh);

        return $jsonFile;
    }

    /**
     * @param $filePath
     * @return null|string|string[]
     * @throws Exception
     */
    public function cleanAnnotations($filePath)
    {
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -ca '
            . escapeshellarg($filePath), $retValue, $retVar);

        if (count($retValue)) {
            throw new Exception('error while cleaning annotations from brochure: ' . $filePath . ' - ' . implode("\n", $retValue));
        }

        return $filePath;
    }

    /**
     * Gibt Annotationsinformationen aus pdf zurück
     *
     * @param string $filePath
     * @return mixed
     * @throws Exception
     */
    public function getAnnotationInfos($filePath)
    {
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -a '
            . escapeshellarg($filePath), $retValue, $retVar);

        if (preg_match('#^Usage\s*of#', $retValue[0])) {
            throw new Exception('error while getting annotation informations for brochure: ' . $filePath . '-' . implode("\n", $retValue));
        }

        return json_decode(end($retValue));
    }

    /**
     *
     * @param string $filePath
     * @return string
     * @throws Exception
     */
    public function createPdf($filePath)
    {
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -c '
            . escapeshellarg($filePath), $retValue, $retVar);

        if (preg_match('#^Usage\s*of#', $retValue[0])) {
            throw new Exception('error while creating pdf for image: ' . $filePath . '-' . implode("\n", $retValue));
        }

        return $retValue;
    }

    /**
     * Fügt ein PDF in ein anderes PDF ein
     *
     * @param string $inputPDF Pfad zur PDF in welches die Seiten eingefügt werden sollen
     * @param string $insertPDF Pfad zur PDF welches eingefügt werden soll
     * @param int $insertAfterPage Seite, nach welcher die Seiten im Ausgangs-PDF eingefügt werden sollen
     * @return string Pfad zur den neuen PDF-Dateien
     * @throws Zend_Exception
     */
    public function insert($inputPDF, $insertPDF, $insertAfterPage)
    {
        $logger = Zend_Registry::get('logger');

        if ($insertAfterPage < 1) {
            $logger->log('Couldn\'t insert PDF before page 1', Zend_Log::ERR);
            return false;
        }

        $outputFile = dirname($inputPDF) . '/' . basename($inputPDF, '.pdf') . '_insert.pdf';

        $cmd = 'java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -n ' . escapeshellarg($inputPDF)
            . ' ' . escapeshellarg($insertPDF) . ' ' . ' ' . escapeshellarg($outputFile) . ' ' . $insertAfterPage;

        exec($cmd, $result, $returnVar);

        if ($returnVar == 0) {
            $logger->log('inserting new page(s) finished successfully.' . "\n", Zend_Log::INFO);
        } else {
            throw new Exception("inserting new page(s) failed ($returnVar): " . implode("\n", $result));
        }

        return $outputFile;
    }

    /**
     * Ermittelt die Anzahl der Seiten eines PDF
     *
     * @param string $inputPDF Pfad zur PDF in welches die Seiten gezählt werden sollen
     * @return int Anzahl der Seiten im PDf
     */
    public function getPageCount($inputPDF)
    {
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -g '
            . escapeshellarg($inputPDF), $retValue, $retVar);

        if (preg_match('#^Usage\s*of#', $retValue[0])) {
            throw new Exception('error while getting general informations for brochure: ' . $inputPDF . '-' . implode("\n", $retValue));
        }

        return json_decode(end($retValue))->pageCount;
    }

    /**
     * Auslesen der Links des Template-Pdfs und Übertragung auf andere
     *
     * @param string $pathPdfTemplate Template-PDF aus dem die Links ausgelesen werden
     * @param array $aPathPdfToLink PDFs auf die die Links übertragen werden sollen
     * @return array $aLinkedFiles
     * @throws Exception
     */
    public function copyLinks($pathPdfTemplate, $aPathPdfToLink = array())
    {
        $aAnnotInfos = $this->getAnnotationInfos($pathPdfTemplate);

        $aAnnotsReadable = array();
        foreach ($aAnnotInfos as $singleAnnot) {
            $aAnnotsReadable[] = array(
                'width' => $singleAnnot->width,
                'height' => $singleAnnot->height,
                'page' => $singleAnnot->page,
                'startX' => $singleAnnot->rectangle->startX,
                'startY' => $singleAnnot->rectangle->startY,
                'endX' => $singleAnnot->rectangle->endX,
                'endY' => $singleAnnot->rectangle->endY,
                'maxX' => $singleAnnot->maxX,
                'maxY' => $singleAnnot->maxY,
                "link" => $singleAnnot->url
            );
        }

        $jsonFile = APPLICATION_PATH . '/../public/files/template_' . date('dmYHim') . '.json';
        file_put_contents($jsonFile, json_encode($aAnnotsReadable));

        $aLinkedPdfs = array();
        foreach ($aPathPdfToLink as $singlePdfToLink) {
            $aLinkedPdfs[] = $this->setAnnotations($singlePdfToLink, $jsonFile);
        }

        return $aLinkedPdfs;
    }

    /**
     *
     */
    public function splitPdf($filePath)
    {
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -u '
            . escapeshellarg($filePath), $retValue, $retVar);

        if (preg_match('#^Usage\s*of#', $retValue[0])) {
            throw new Exception('error while splitting pdf: ' . $filePath . '-' . implode("\n", $retValue));
        }

        return $retValue;
    }

    /**
     * trennt die erste Doppelseite auf und fügt die linke Seite hinten an
     */
    public function separateFirstLastPage($filePath)
    {
        $returnFilePath = preg_replace('#(\.pdf)$#', '_splitted$1', $filePath);
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -l '
            . escapeshellarg($filePath) . ' ' . escapeshellarg($returnFilePath), $retValue, $retVar);

        if (preg_match('#^Usage\s*of#', $retValue[0])) {
            throw new Exception('error while separating first and last page of the pdf: ' . $filePath . '-' . implode("\n", $retValue));
        }

        return $returnFilePath;
    }

    /**
     * Modifiziert Links im Prospekt
     */
    public function modifyLinks($filePath, $jsonFilePath, $addModificationDate = FALSE)
    {
        $modificationDate = '';
        if ($addModificationDate) {
            usleep(2000000);
            $modificationDate = '_' . date('YmdHis');
        }
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -m '
            . escapeshellarg($filePath) . ' ' . escapeshellarg($jsonFilePath) . ' ' . preg_replace('#\.pdf#', $modificationDate . '_modified.pdf', $filePath), $retValue, $retVar);

        if (preg_match('#^Usage\s*of#', $retValue[0])) {
            throw new Exception('error while modifying links: ' . $filePath . '-' . implode("\n", $retValue));
        }

        return preg_replace('#\.pdf#', $modificationDate . '_modified.pdf', $filePath);
    }

    /**
     * Extrahiert den Text aus dem gegebenen Prospekt
     */
    public function extractText($filePath)
    {
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -x '
            . escapeshellarg($filePath), $retValue, $retVar);

        if (preg_match('#^Usage\s*of#', $retValue[0])) {
            throw new Exception('error while extracting text: ' . $filePath . '-' . implode("\n", $retValue));
        }

        return $retValue[0];
    }


    public function extractImages($pdfFilePath, $dpi)
    {
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -p "'
            . escapeshellarg($pdfFilePath) . '" ' . $dpi, $retValue, $retVar);

        if (preg_match('#^Usage\s*of#', $retValue[0])) {
            throw new Exception('error while extracting image: ' . $pdfFilePath . '-' . implode("\n", $retValue));
        }

        $aImages = json_decode($retValue[0]);
        $sZip = new Marktjagd_Service_Output_Archive();
        $destination = preg_replace('#\.pdf#', '.zip', $pdfFilePath);
        $sZip->zipFiles($aImages, $destination);

        return $destination;
    }

    public function addElements($templateFile, $jsonFilePath)
    {
        exec('java -Djava.awt.headless=true -jar ' . APPLICATION_PATH . '/../tools/pdfbox/pdfbox-simple-jar-with-dependencies.jar -e '
            . escapeshellarg($templateFile) . ' ' . escapeshellarg($jsonFilePath) . ' ' . preg_replace('#\.pdf#', '_added.pdf', $templateFile), $retValue, $retVar);

        if (isset($retValue[0]) && preg_match('#^Usage\s*of#', $retValue[0])) {
            throw new Exception('error while adding elements: ' . $templateFile . '-' . implode("\n", $retValue));
        }
        if($retVar != 0) {
            throw new Exception('Return value:' . $retVar);
        }

        return preg_replace('#\.pdf#', '_added.pdf', $templateFile);
    }

    /**
     * inserts a Survey to the Brochure/PDF
     * The name of the survey pdf must contain "Umfrage" or "Mafo".
     *
     * @param $filePath
     * @param int $insertAfterPage
     * @param bool $forceInsert
     * @return string
     * @throws Zend_Exception
     */
    public function implementSurvey($filePath, $insertAfterPage = 2, $forceInsert = true)
    {
        $localPath = dirname($filePath);

        if (!preg_match('#files\/(?:ftp|http|pdf)\/(\d+)\/#', $localPath, $companyIdMatch)) {
            throw new Exception('unable to get company id from file path: ' . $filePath);
        }

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect($companyIdMatch[1]);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#(?:umfrage|mafo)[^\.]*\.pdf#i', $singleFile)) {
                $localSurveyPath = $sFtp->downloadFtpToDir($singleFile, $localPath . '/');
                break;
            }
        }

        if (!isset($localSurveyPath)) {
            return $filePath;
        }

        $sFtp->close();

        if ($this->getPageCount($filePath) <= $insertAfterPage) {
            if ($forceInsert) {
                return $this->merge([$filePath, $localSurveyPath], dirname($filePath) . '/');
            } else {
                throw new Exception('unable to set the Page on Place, Brochure has too few Pages');
            }
        } else {
            return $this->insert($filePath, $localSurveyPath, $insertAfterPage);
        }
    }

    /**
     * @param array $files
     * @param string $localPath
     * @return string
     * @throws Exception
     */
    public function getPdfFromImageArray(array $files, string $localPath): string
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();

        $aPdfsToMerge = [];
        foreach ($files as $PageId => $fileUrl) {
            $filename = pathinfo($fileUrl, PATHINFO_BASENAME);
            if (!in_array($filename, scandir($localPath))) {
                $sHttp->getRemoteFile($fileUrl, $localPath);
            }
            $this->createPdf($localPath . $filename);
            $aPdfsToMerge[$PageId] = $localPath . pathinfo($filename, PATHINFO_FILENAME) . '.pdf';
        }

        return $this->merge($aPdfsToMerge, $localPath);
    }

    /**
     * @param string $filePathPdf
     * @param string $companyId
     * @param $aCheckedInfos
     * @return string
     * @throws Zend_Exception
     */
    public function implementSurveyFromSpreadsheet($filePathPdf, $companyId, $aCheckedInfos)
    {
        $localSurveyFile = $this->checkIfSurveyAlreadyExists($companyId, $aCheckedInfos);

        $sPdf = new Marktjagd_Service_Output_Pdf();

        $localFilePathPdf = $filePathPdf;
        if (preg_match('#amazonaws.*\/([^\/]+?)\/([^\/]+)#', $filePathPdf, $suffixFileMatch)) {
            $localPath = APPLICATION_PATH . '/../public/files/survey/' . $companyId . '/' . date('Y-m-d-H-i-s') . '/';
            if (!is_dir($localPath)) {
                if (!mkdir($localPath, 0775, true)) {
                    throw new Exception($companyId . ': unable to create folder.');
                }
            }
            $sS3 = new Marktjagd_Service_Output_S3File($suffixFileMatch[1], $suffixFileMatch[2]);
            $localFilePathPdf = $sS3->getFileFromBucket($filePathPdf, $localPath);
        } elseif(preg_match('#^http#', $localFilePathPdf)) {
            $sHttp = new Marktjagd_Service_Transfer_Http();
            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            $localFilePathPdf = $sHttp->getRemoteFile($filePathPdf, $localPath);
        }

        if ($this->getPageCount($localFilePathPdf) <= $aCheckedInfos['insert after page']) {
            $localFilePathPdf = $this->merge([$localFilePathPdf, $localSurveyFile], $localPath);

        } else {
            $localFilePathPdf = $sPdf->insert(
                $localFilePathPdf,
                $localSurveyFile,
                $aCheckedInfos['insert after page']
            );
        }


        $fileNameLinked = $localFilePathPdf;

        if (!preg_match('#amazonaws#', $filePathPdf)) {
            $sS3 = new Marktjagd_Service_Output_S3File('/pdf/', basename($localFilePathPdf));
            $fileNameLinked = $sS3->saveFileInS3($localFilePathPdf);
        }

        return $fileNameLinked;
    }

    /**
     * @param $companyId
     * @param $aCheckedInfos
     * @return bool|string
     * @throws Exception
     */
    public function checkIfSurveyAlreadyExists($companyId, $aCheckedInfos)
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();
        if (!is_file(APPLICATION_PATH . '/../public/files/survey/' . $companyId . '/' . $aCheckedInfos['row'] . '/survey_blank_linked.pdf')) {
            Zend_Debug::dump($companyId . ': creating survey file.');
            $sHttp = new Marktjagd_Service_Transfer_Http();
            $localPath = APPLICATION_PATH . '/../public/files/survey/' . $companyId . '/' . $aCheckedInfos['row'] . '/';
            if (!is_dir($localPath)) {
                if (!mkdir($localPath, 0775, true)) {
                    throw new Exception($companyId . ': unable to create folder.');
                }
            }
            $localSurveyFile = $sHttp->getRemoteFile('https://og-marketing-public.s3.eu-west-1.amazonaws.com/survey_blank.pdf', $localPath);

            $aAnnotInfos = [
                [
                    'page' => 0,
                    'height' => 841.89,
                    'width' => 595.276,
                    'startX' => 395,
                    'endX' => 435,
                    'startY' => 90,
                    'endY' => 130,
                    'link' => $aCheckedInfos['url to survey']
                ]
            ];

            $coordFileName = $localPath . 'coordinates_' . $companyId . '_survey.json';
            $fh = fopen($coordFileName, 'w+');
            fwrite($fh, json_encode($aAnnotInfos));
            fclose($fh);

            $localSurveyFile = $sPdf->setAnnotations($localSurveyFile, $coordFileName);
        } else {
            Zend_Debug::dump($companyId . ': survey file already exists.');
            $localSurveyFile = APPLICATION_PATH . '/../public/files/survey/' . $companyId . '/' . $aCheckedInfos['row'] . '/survey_blank_linked.pdf';
        }

        return $localSurveyFile;
    }
}
