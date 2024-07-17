<?php

/**
 * Allgemeiner FTP-Crawler
 *
 * Class Crawler_Generic_Ftp
 */
class Crawler_Generic_Ftp extends Crawler_Generic_Abstract
{
    /**
     * Methode, die den Crawlingprozess initiert
     *
     * @param int $companyId
     * @param string $type
     * @param string $prefix Unterordner ohne Slashes
     * @return bool|string
     */
    function crawl($companyId, $type, $prefix = '')
    {
        // Pattern für FTP-Download aus der DB ermitteln
        $sTriggerConfig = new Marktjagd_Database_Service_TriggerConfig();
        $eTriggerConfig = $sTriggerConfig->findByCompanyTriggerTypeCrawlerType(
            $companyId,
            Marktjagd_Entity_TriggerType::$TYPE_FTP,
            $type
        );

        if (!strlen($eTriggerConfig->getIdTriggerConfig())) {
            $this->_logger->log('generic ftp-crawler for company ' . $companyId . "\n"
                . 'unable to get filename pattern for crawler type ' . $type, Zend_Log::CRIT);
            return $this->_response->generateResponseByFileName(null);
        }

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        // Erstellen des lokalen Verzeichnisses
        $locFolderName = $sFtp->generateLocalDownloadFolder($companyId);

        // Datei auf FTP im Ordner des UN finden
        $patternFileName = $eTriggerConfig->getPatternFileName();

        $sFtp->connect();

        $path = '/' . $companyId . '/';
        if (strlen($prefix)) {
            $path = '/' . $prefix . $path;
        }

        $aFiles = $sFtp->listFiles($path, $patternFileName, true);

        if (count($aFiles) != 1) {
            $this->_logger->log('generic ftp-crawler for company ' . $companyId . "\n"
                . 'find null or too much files (count:' . count($aFiles) . ') for pattern '
                . $patternFileName .  ' on ftp', Zend_Log::CRIT);
            return $this->_response->generateResponseByFileName(null);
        }

        //FTP-Download der Dateien
        $remFilePath = $aFiles[0];
        $localFileName = $sFtp->downloadFtpToDir($remFilePath, $locFolderName);

        if (!$localFileName) {
            $this->_logger->log('generic ' . $type . ' ftp-crawler for company ' . $companyId . "\n"
                . 'unable to download file from ' . $remFilePath . ' to ' . $locFolderName, Zend_Log::CRIT);
            return $this->_response->generateResponseByFileName(null);
        }

        // Transformation der FTP-Pfade und ggfs. Download der Dateien
        $sMjCsv = new Marktjagd_Service_Input_MarktjagdCsv();
        $cMjCsv = $sMjCsv->convertToCollection($localFileName, $type);
        $sFtp->transformCollection($cMjCsv, $path, $type, $locFolderName);

        $sCsv = null;
        switch ($type) {
            case 'articles':
                $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
                break;
            case 'stores':
                $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
                break;
            case 'brochures':
                $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
                break;
            default:
                $this->_logger->log('generic ' . $type . ' ftp-crawler for company ' . $companyId . "\n"
                    . 'wrong type given for generating csv: ' . $type, Zend_Log::CRIT);
                break;
        }

        $fileName = $sCsv->generateCsvByCollection($cMjCsv);
        return $this->_response->generateResponseByFileName($fileName);
    }
}