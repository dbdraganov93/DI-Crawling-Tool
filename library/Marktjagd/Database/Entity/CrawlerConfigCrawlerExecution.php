<?php

class Marktjagd_Database_Entity_CrawlerConfigCrawlerExecution extends Marktjagd_Database_Entity_Abstract
{
    // table fields
    protected $_idCrawlerConfigXCrawlerExecution;
    protected $_CrawlerConfig_idCrawlerConfig;
    protected $_CrawlerExecution_idCrawlerExecution;

    /**
     * Contains mapping of table columns to function
     *
     * @var array
     */
    protected $_aColumnMap = array('idCrawlerConfigXCrawlerExecution' => 'IdCrawlerConfigXCrawlerExecution',
                                   'CrawlerConfig_idCrawlerConfig' => 'CrawlerConfigIdCrawlerConfig',
                                   'CrawlerExecution_idCrawlerExecution' => 'CrawlerExecutionIdCrawlerExecution');

    /**
     * Relationship map
     *
     * @var array
     */
    protected $_aRelationMap = array('CrawlerConfig' => 'Marktjagd_Database_Entity_CrawlerConfig',
                                     'CrawlerExecution' => 'Marktjagd_Database_Entity_CrawlerExecution');

    /**
     * Relation property map
     *
     * @var array
     */
    protected $_aRelationPropertyMap = array('CrawlerConfig' => 'CrawlerConfig',
                                             'CrawlerExecution' => 'CrawlerExecution');

    /**
     * Relationship object for table CrawlerConfig
     *
     * @var Marktjagd_Database_Entity_CrawlerConfig
     */
    protected $_oCrawlerConfig;

    /**
     * Relationship object for table CrawlerExecution
     *
     * @var Marktjagd_Database_Entity_CrawlerExecution
     */
    protected $_oCrawlerExecution;

    /**
     * Set entity crawlerconfig
     *
     * @param Marktjagd_Database_Entity_CrawlerConfig         $oCrawlerConfig
     *
     * @return Marktjagd_Database_Entity_CrawlerConfigCrawlerExecution
     */
    public function setCrawlerConfig(Marktjagd_Database_Entity_CrawlerConfig $oCrawlerConfig)
    {
        $this->_oCrawlerConfig = $oCrawlerConfig;
        return $this;
    }

    /**
     * Return entity crawlerconfig
     *
     * @return Marktjagd_Database_Entity_CrawlerConfig
     */
    public function getCrawlerConfig()
    {
        return $this->_oCrawlerConfig;
    }

    /**
     * Set entity crawlerexecution
     *
     * @param Marktjagd_Database_Entity_CrawlerExecution         $oCrawlerExecution
     *
     * @return Marktjagd_Database_Entity_CrawlerConfigCrawlerExecution
     */
    public function setCrawlerExecution(Marktjagd_Database_Entity_CrawlerExecution $oCrawlerExecution)
    {
        $this->_oCrawlerExecution = $oCrawlerExecution;
        return $this;
    }

    /**
     * Return entity crawlerexecution
     *
     * @return Marktjagd_Database_Entity_CrawlerExecution
     */
    public function getCrawlerExecution()
    {
        return $this->_oCrawlerExecution;
    }


    /**
     * Set idCrawlerConfigXCrawlerExecution, value is casted to int
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_CrawlerConfigCrawlerExecution
     */
    public function setIdCrawlerConfigXCrawlerExecution($mValue)
    {
        $this->_idCrawlerConfigXCrawlerExecution = (int) $mValue;
        return $this;
    }

    /**
     * Returns idCrawlerConfigXCrawlerExecution
     *
     * @return int idCrawlerConfigXCrawlerExecution
     */
    public function getIdCrawlerConfigXCrawlerExecution()
    {
        return $this->_idCrawlerConfigXCrawlerExecution;
    }

    /**
     * Set CrawlerConfig_idCrawlerConfig, value is casted to int
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_CrawlerConfigCrawlerExecution
     */
    public function setCrawlerConfigIdCrawlerConfig($mValue)
    {
        $this->_CrawlerConfig_idCrawlerConfig = (int) $mValue;
        return $this;
    }

    /**
     * Returns CrawlerConfig_idCrawlerConfig
     *
     * @return int CrawlerConfig_idCrawlerConfig
     */
    public function getCrawlerConfigIdCrawlerConfig()
    {
        return $this->_CrawlerConfig_idCrawlerConfig;
    }

    /**
     * Set CrawlerExecution_idCrawlerExecution, value is casted to int
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_CrawlerConfigCrawlerExecution
     */
    public function setCrawlerExecutionIdCrawlerExecution($mValue)
    {
        $this->_CrawlerExecution_idCrawlerExecution = (int) $mValue;
        return $this;
    }

    /**
     * Returns CrawlerExecution_idCrawlerExecution
     *
     * @return int CrawlerExecution_idCrawlerExecution
     */
    public function getCrawlerExecutionIdCrawlerExecution()
    {
        return $this->_CrawlerExecution_idCrawlerExecution;
    }

    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_CrawlerConfigCrawlerExecution
     */
    public function getMapper()
    {
        return parent::getMapper();
    }

    /**
     * Saves data to database If the primary key is set,
     * data will be updated.
     *
     * @param bool $bNull Save also null values
     *
     * @return void
     */
    public function save($bNull = false)
    {
        $this->getMapper()->save($this, $bNull);
    }

    /**
     * Loads the data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId)
    {
        return $this->getMapper()->find($mId, $this);
    }
}