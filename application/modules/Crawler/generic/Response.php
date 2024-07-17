<?php
/**
 * Beschreibt das Response-Objekt, welches jeder Crawler zurückliefern muss
 *
 * Class Crawler_Generic_Response
 */
class Crawler_Generic_Response
{
    const PROCESSING = 1;
    const SUCCESS = 2;
    const FAILED = 3;
    const SUCCESS_NO_IMPORT = 4;
    const COULDNT_START = 5;
    const WAITING = 6;
    const IMPORT_FAILURE_ADD = 7;
    const IMPORT_PROCESSING = 8;
    const IMPORT_FAILURE = 9;
    const IMPORT_SUCCESS = 10;
    const SUCCESS_CHANGED = 11;

    /* @var int */
    protected $_loggingCode;

    /* @var string */
    protected $_fileName;

    /* @var boolean */
    protected $_isImport;

    /* @var int */
    protected $_importId;

    /* @var int */
    protected $_countElements;

    /**
     * @param string $fileName
     * @return Crawler_Generic_Response
     */
    public function setFileName($fileName)
    {
        $this->_fileName = $fileName;
        return $this;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->_fileName;
    }

    /**
     * @param boolean $isImport
     * @return Crawler_Generic_Response
     */
    public function setIsImport($isImport)
    {
        $this->_isImport = $isImport;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getIsImport()
    {
        return $this->_isImport;
    }

    /**
     * @param int $loggingCode
     * @return Crawler_Generic_Response
     */
    public function setLoggingCode($loggingCode)
    {
        $this->_loggingCode = $loggingCode;
        return $this;
    }

    /**
     * @return int
     */
    public function getLoggingCode()
    {
        return $this->_loggingCode;
    }

    /**
     * @param int $importId
     * @return Crawler_Generic_Response
     */
    public function setImportId($importId)
    {
        $this->_importId = $importId;
        return $this;
    }

    /**
     * @return int
     */
    public function getImportId()
    {
        return $this->_importId;
    }

    /**
     * @param $countElements
     * @return $this
     */
    public function setCountElements($countElements)
    {
        $this->_countElements = $countElements;
        return $this;
    }

    /**
     * @return int
     */
    public function getCountElements()
    {
        return $this->_countElements;
    }

    /**
     * Generiert die Response anhand des Dateinamens
     *
     * @param string $fileName absoluter Pfad zur Datei
     * @return $this
     */
    public function generateResponseByFileName($fileName, $countElements = 0)
    {
        if ($fileName) {
            $this->_fileName = $fileName;
            $this->_isImport = true;
            $this->_loggingCode = Crawler_Generic_Response::SUCCESS;
            $this->_countElements = $countElements;
        } else {
            $this->_isImport = false;
            $this->_loggingCode = Crawler_Generic_Response::FAILED;
        }
        
        return $this;
    }

    /**
     * @param null $logId
     * @throws Zend_Exception
     */
    public function save($logId = null)
    {
        $crawlerLog = new Marktjagd_Database_Entity_CrawlerLog();
        $crawlerLog->setIdCrawlerLog($logId)
                   ->setIdCrawlerLogType($this->getLoggingCode());
        
        if ($this->getLoggingCode() == Crawler_Generic_Response::SUCCESS
            || $this->getLoggingCode() == Crawler_Generic_Response::SUCCESS_NO_IMPORT
            || $this->getLoggingCode() == Crawler_Generic_Response::IMPORT_FAILURE_ADD
            || $this->getLoggingCode() == Crawler_Generic_Response::COULDNT_START
            || $this->getLoggingCode() == Crawler_Generic_Response::FAILED
            || $this->getLoggingCode() == Crawler_Generic_Response::SUCCESS_CHANGED
        ) {
            $crawlerLog->setEnd(date('Y-m-d H:i:s'));
        }

        $loggerMock = Zend_Registry::get('loggerMock');

        // Falls Status nicht od. falsch gesetzt wurde => korrigieren
        if (count($loggerMock->events)
            && ($crawlerLog->getIdCrawlerLogType() == Crawler_Generic_Response::SUCCESS
                || $crawlerLog->getIdCrawlerLogType() == Crawler_Generic_Response::SUCCESS_NO_IMPORT)
        ) {
            $crawlerLog->setIdCrawlerLogType(Crawler_Generic_Response::FAILED);
        }

        /* @var $loggerMock Zend_Log_Writer_Mock */
        foreach ($loggerMock->events as $errorMsg) {
            // Prüfen ob Errormessage noch nicht gesetzt wurde => hinzufügen
            if ($crawlerLog->getErrorMessage() === null || strpos($crawlerLog->getErrorMessage(), $errorMsg) === false) {
                $crawlerLog->addErrorMessage($errorMsg['message']);
            }
        }

        if ($crawlerLog->getIdCrawlerLogType() == Crawler_Generic_Response::IMPORT_PROCESSING) {
            $crawlerLog->setImportStart(date('Y-m-d H:i:s'));
            $crawlerLog->setImportId($this->getImportId());
        }
        $crawlerLog->save();

    }
}