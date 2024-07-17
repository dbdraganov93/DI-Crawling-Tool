<?php

/**
 * Klasse, die eine gecrawlte Webseite beschreibt
 *
 * Class Marktjagd_Entity_Page
 */
class Marktjagd_Entity_Page {

    /**
     * Gibt an, ob robots.txt ignoriert werden soll
     * @var bool
     */
    protected $ignoreRobots;

    /**
     * String des Headers
     * @var string
     */
    protected $responseHeader;

    /**
     * String des Response Body
     * @var string
     */
    protected $responseBody;
    
    /**
     * String des Response Body json-dekodiert
     * @var string
     */
    protected $responseAsJson;

    /**
     * Fehlercode
     * @var int
     */
    protected $errorCode;

    /**
     * Fehlerstring
     * @var string
     */
    protected $errorString;

    /**
     * Art der Anfrage [GET|POST]
     * @var string
     */
    protected $method;

    /**
     * Flag um die Header-Informationen Mitzuschreiben und während der Laufzeit auszulesen.
     * @var bool
     */
    protected $storeHeaderInformations;

    /**
     * Name der Datei, in der die Header-Informationen bei einem Seitenaufruf zwischengespeichert werden
     * @var string
     */
    protected $header;

    /**
     * Flag um zu bestimmen, ob Cookies benutzt werden sollen
     * @var bool
     */
    protected $useCookies;

    /**
     * Flag zum Konvertieren des Zeichensatzes in UTF-8
     * @var boolean
     */
    protected $alwaysConvertCharset;

    /**
     * Flag zum Konvertieren aller HTML-Entities
     * @var boolean
     */
    protected $alwaysHtmlDecode;

    /**
     * Flag zum Entfernen der HTML-Kommentare
     * @var bool
     */
    protected $alwaysStripComments;

    /**
     * Flag zum Entfernen der Zeilenumbrüche
     * @var bool
     */
    protected $alwaysStripNewLines;

    /**
     * Flag zum Entfernen der Leerzeichen
     *
     * @var bool
     */
    protected $alwaysStripWhiteSpace;

    /**
     * Anzahl maximaler Ladeversuche
     * @var int
     */
    protected $loadTries;

    /**
     * Zeitüberschreitung für Verbindungen (Sekunden)
     * @var int
     */
    protected $timeout;

    /**
     * HTTP Client
     * @var Zend_Http_Client
     */
    protected $client;
    protected $userAgent;

    /**
     * 
     * @param bool $rawMode Parameter zum De-/Aktivieren aller Formatierungen
     */
    public function __construct($rawMode) {
        $this->client = new Zend_Http_Client();
        $this->ignoreRobots = false;
        $this->errorCode = null;
        $this->errorString = null;
        $this->method = 'GET';
        $this->storeHeaderInformations = false;
        $this->header = null;
        $this->useCookies = false;
        $this->alwaysConvertCharset = true;
        $this->alwaysHtmlDecode = true;
        $this->alwaysStripComments = true;
        $this->alwaysStripNewLines = true;
        $this->alwaysStripWhiteSpace = true;
        $this->loadTries = 10;
        $this->timeout = 60;
        $this->userAgent = 'Offerista-Suchdienst (+https://www.offerista.com/suchdienst/)';

        if ($rawMode) {
            $this->alwaysConvertCharset = false;
            $this->alwaysHtmlDecode = false;
            $this->alwaysStripComments = false;
            $this->alwaysStripNewLines = false;
            $this->alwaysStripWhiteSpace = false;
        }
    }

    /**
     * @return string
     */
    public function getResponseBody() {
        return $this->responseBody;
    }
    
    /**
     * @return array
     */
    public function getResponseAsJson() {
        return json_decode($this->responseBody);
    }

    /**
     * @return string
     */
    public function getResponseHeader() {
        return $this->responseHeader;
    }

    /**
     * @param string $responseHeader
     * @return Marktjagd_Entity_Page
     */
    public function setResponseHeader($responseHeader) {
        $this->responseHeader = $responseHeader;
        return $this;
    }

    /**
     * @param string $responseBody
     * @return Marktjagd_Entity_Page
     */
    public function setResponseBody($responseBody) {
        $this->responseBody = $responseBody;
        return $this;
    }

    /**
     * @param boolean $ignoreRobots
     * @return $this
     */
    public function setIgnoreRobots($ignoreRobots) {
        $this->ignoreRobots = $ignoreRobots;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getIgnoreRobots() {
        return $this->ignoreRobots;
    }

    /**
     * @param boolean $alwaysConvertCharset
     * @return $this
     */
    public function setAlwaysConvertCharset($alwaysConvertCharset) {
        $this->alwaysConvertCharset = $alwaysConvertCharset;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getAlwaysConvertCharset() {
        return $this->alwaysConvertCharset;
    }

    /**
     * @param boolean $alwaysHtmlDecode
     * @return $this
     */
    public function setAlwaysHtmlDecode($alwaysHtmlDecode) {
        $this->alwaysHtmlDecode = $alwaysHtmlDecode;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getAlwaysHtmlDecode() {
        return $this->alwaysHtmlDecode;
    }

    /**
     * @param boolean $alwaysStripComments
     * @return $this
     */
    public function setAlwaysStripComments($alwaysStripComments) {
        $this->alwaysStripComments = $alwaysStripComments;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getAlwaysStripComments() {
        return $this->alwaysStripComments;
    }

    /**
     * @param boolean $alwaysStripNewLines
     * @return $this
     */
    public function setAlwaysStripNewLines($alwaysStripNewLines) {
        $this->alwaysStripNewLines = $alwaysStripNewLines;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getAlwaysStripNewLines() {
        return $this->alwaysStripNewLines;
    }

    /**
     * @param boolean $alwaysStripWhiteSpace
     * @return $this
     */
    public function setAlwaysStripWhiteSpace($alwaysStripWhiteSpace) {
        $this->alwaysStripWhiteSpace = $alwaysStripWhiteSpace;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getAlwaysStripWhiteSpace() {
        return $this->alwaysStripWhiteSpace;
    }

    /**
     * @param int $errorCode
     * @return $this
     */
    public function setErrorCode($errorCode) {
        $this->errorCode = $errorCode;
        return $this;
    }

    /**
     * @return int
     */
    public function getErrorCode() {
        return $this->errorCode;
    }

    /**
     * @param string $errorString
     * @return $this
     */
    public function setErrorString($errorString) {
        $this->errorString = $errorString;
        return $this;
    }

    /**
     * @return string
     */
    public function getErrorString() {
        return $this->errorString;
    }

    /**
     * @param string $header
     * @return $this
     */
    public function setHeader($header) {
        $this->header = $header;
        return $this;
    }

    /**
     * @return string
     */
    public function getHeader() {
        return $this->header;
    }

    /**
     * @param boolean $useCookies
     * @return $this
     */
    public function setUseCookies($useCookies) {
        $this->useCookies = $useCookies;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getUseCookies() {
        return $this->useCookies;
    }

    /**
     * @param int $loadTries
     * @return $this
     */
    public function setLoadTries($loadTries) {
        $this->loadTries = $loadTries;
        return $this;
    }

    /**
     * @return int
     */
    public function getLoadTries() {
        return $this->loadTries;
    }

    /**
     * @param string $method
     * @return $this
     */
    public function setMethod($method) {
        $this->method = $method;
        return $this;
    }

    /**
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * @param boolean $storeHeaderInformations
     * @return $this
     */
    public function setStoreHeaderInformations($storeHeaderInformations) {
        $this->storeHeaderInformations = $storeHeaderInformations;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getStoreHeaderInformations() {
        return $this->storeHeaderInformations;
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setTimeout($timeout) {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * @return int
     */
    public function getTimeout() {
        return $this->timeout;
    }

    /**
     * @param Zend_Http_Client $client
     * @return Marktjagd_Entity_Page
     */
    public function setClient($client) {
        $this->client = $client;
        return $this;
    }

    /**
     * @return Zend_Http_Client
     */
    public function getClient() {
        return $this->client;
    }

    /**
     * Setzt den User-Agent
     *
     * @param string $userAgent
     * @return Marktjagd_Entity_Page
     */
    public function setUserAgent($userAgent) {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Gibt den User-Agent des Page Objektes zurück
     *
     * @return string
     */
    public function getUserAgent() {
        return $this->userAgent;
    }

}
