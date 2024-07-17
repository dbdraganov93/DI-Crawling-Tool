<?php

/**
 * Klasse von der alle Crawler-Klassen mind. ableiten müssen
 *
 * Class Crawler_Generic_Abstract
 */
class Crawler_Generic_Abstract
{
    /**
     * Loggingobjekt
     *
     * @var Zend_Log
     */
    protected $_logger;

    /**
     * Responseobjekt
     *
     * @var Crawler_Generic_Response
     */
    protected $_response;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->_logger = Zend_Registry::get('logger');
        $this->_response = new Crawler_Generic_Response();
    }
}