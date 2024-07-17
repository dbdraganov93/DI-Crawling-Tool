<?php

/**
 * Service zum Schreiben eines Files
 */
class Marktjagd_Service_Output_File {
    protected $_fileHandle;
    protected $_modus;
    protected $_filePath;
    protected $_s3Config;

    /**
     * Beim Erzeugen des Services wird versucht das Filehandle zu öffnen, sofern die Datei lokal gespeichert werden soll.
     * @param string $path Pfad, der nach /public/files spezifiert werden soll (mit /)
     * @param string $fileName Dateiname incl. Endung
     * @param string $modus Modus mit dem die Datei geschrieben werden soll (append, write)
     */
    public function __construct($path, $fileName, $modus = 'w')
    {
        $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $this->_s3Config = $configCrawler->crawler->s3;
        
        if ($this->_s3Config->active) {
            // Let S3File service handle the details.
            $this->_fileHandle = new Marktjagd_Service_Output_S3File($path, $fileName);
        } else {
            $success = false;
            $filePath = APPLICATION_PATH . '/../public/files' . $path;
            // Sichergehen, dass der Ordner existiert und beschreibbar ist
            if (is_writable($filePath)) {
                $this->_filePath = $filePath . $fileName;
                if ($fileHandle = fopen($this->_filePath, $modus)) {
                    $this->_fileHandle = $fileHandle;
                    $success = true;
                }
            }

            if (!$success) {
                $logger = Zend_Registry::get('logger');
                $logger->log('Datei ' . $filePath . $fileName . ' konnte nicht geöffnet werden.', Zend_Log::CRIT);
            }
        }
    }

    /**
     * Schließen des Filehandles
     */
    public function __destruct()
    {
        if (!$this->_s3Config->active && $this->_fileHandle) {
            fclose($this->_fileHandle);
        }
    }

    /**
     * Speichert den übergebenen Content in eine Datei oder S3 Objekt und gibt den Pfad dieser Datei zurück
     * Im Fehlerfall wird false zurückgegeben
     *
     * @param string $content Inhalt, der in die Datei geschrieben werden soll
     * @return boolean|string
     */
    public function saveContentInFile($content)
    {
        if ($this->_s3Config->active) {
            // Let S3File service handle the details.
            return $this->_fileHandle->saveContentInFile($content);
        } else {
            // Schreibe Content in die geöffnete Datei.
            if (!fwrite($this->_fileHandle, $content)) {
                $logger = Zend_Registry::get('logger');
                $logger->log('In Datei konnte nicht geschrieben werden', Zend_Log::CRIT);
                return false;
            }

            return true;
        }
    }

    /**
     * Generiert aus dem übergebenem Dateipfad die öffentlich erreichbare URL
     *
     * @param $internalUrl
     * @return bool|string
     */
    public static function generatePublicUrl($internalUrl)
    {
        $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        // Can't generate public URL for S3 object from internal path.
        if($configCrawler->crawler->s3->active) return false;

        if (!preg_match('#.*(/files/mjcsv/(articles|brochures|stores)\_.*?\.csv)$#', $internalUrl, $match)) {
            /* @var $logger Zend_Log */
            $logger = Zend_Registry::get('logger');
            $logger->log('invalid filename for generating public url, filename: ' . $internalUrl, Zend_Log::ERR);
            return false;
        }

        return $configCrawler->crawler->publicUrl . $match[1];
    }

    /**
     * Gibt den Pfad zur Datei zurück, wenn es sich um lokale Dateispeicherung handelt.
     * Gibt die URL zur Datei in einem S3 Bucket zurück, wenn S3 Speicehrung aktiviert ist.
     *
     * @return string
     */
    public function getFilePath()
    {   
        if ($this->_s3Config->active) {
            return $this->_fileHandle->getFileURL();
        } else {
            return $this->_filePath;
        }
    }
}
