<?php

class Marktjagd_Database_Entity_TriggerConfig extends Marktjagd_Database_Entity_Abstract {

    // table fields
    protected $_idTriggerConfig;
    protected $_idCompany;
    protected $_idCrawlerType;
    protected $_idTriggerType;
    protected $_patternFileName;
    protected $_idCrawlerConfig;

    /**
     * Contains mapping of table columns to function
     *
     * @var array
     */
    protected $_aColumnMap = array('idTriggerConfig' => 'IdTriggerConfig',
        'idCompany' => 'IdCompany',
        'idCrawlerType' => 'IdCrawlerType',
        'idTriggerType' => 'IdTriggerType',
        'patternFileName' => 'PatternFileName',
        'idCrawlerConfig' => 'IdCrawlerConfig');

    /**
     * Relationship map
     *
     * @var array
     */
    protected $_aRelationMap = array('TriggerType' => 'Marktjagd_Database_Entity_TriggerType',
        'Company' => 'Marktjagd_Database_Entity_Company',
        'CrawlerType' => 'Marktjagd_Database_Entity_CrawlerType');

    /**
     * Relation property map
     *
     * @var array
     */
    protected $_aRelationPropertyMap = array('TriggerType' => 'TriggerType',
        'Company' => 'Company',
        'CrawlerType' => 'CrawlerType');

    /**
     * Relationship object for table TriggerType
     *
     * @var Marktjagd_Database_Entity_TriggerType
     */
    protected $_oTriggerType;

    /**
     * Relationship object for table Company
     *
     * @var Marktjagd_Database_Entity_Company
     */
    protected $_oCompany;

    /**
     * Relationship object for table CrawlerType
     *
     * @var Marktjagd_Database_Entity_CrawlerType
     */
    protected $_oCrawlerType;

    /**
     * Set entity triggertype
     *
     * @param Marktjagd_Database_Entity_TriggerType         $oTriggerType
     *
     * @return Marktjagd_Database_Entity_TriggerConfig
     */
    public function setTriggerType(Marktjagd_Database_Entity_TriggerType $oTriggerType) {
        $this->_oTriggerType = $oTriggerType;
        return $this;
    }

    /**
     * Return entity triggertype
     *
     * @return Marktjagd_Database_Entity_TriggerType
     */
    public function getTriggerType() {
        return $this->_oTriggerType;
    }

    /**
     * Set entity company
     *
     * @param Marktjagd_Database_Entity_Company         $oCompany
     *
     * @return Marktjagd_Database_Entity_TriggerConfig
     */
    public function setCompany(Marktjagd_Database_Entity_Company $oCompany) {
        $this->_oCompany = $oCompany;
        return $this;
    }

    /**
     * Return entity company
     *
     * @return Marktjagd_Database_Entity_Company
     */
    public function getCompany() {
        return $this->_oCompany;
    }

    /**
     * Set entity crawlertype
     *
     * @param Marktjagd_Database_Entity_CrawlerType         $oCrawlerType
     *
     * @return Marktjagd_Database_Entity_TriggerConfig
     */
    public function setCrawlerType(Marktjagd_Database_Entity_CrawlerType $oCrawlerType) {
        $this->_oCrawlerType = $oCrawlerType;
        return $this;
    }

    /**
     * Return entity crawlertype
     *
     * @return Marktjagd_Database_Entity_CrawlerType
     */
    public function getCrawlerType() {
        return $this->_oCrawlerType;
    }

    /**
     * Set idTriggerConfig, value is casted to int
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_TriggerConfig
     */
    public function setIdTriggerConfig($mValue) {
        $this->_idTriggerConfig = $mValue;
        return $this;
    }

    /**
     * Returns idTriggerConfig
     *
     * @return int idTriggerConfig
     */
    public function getIdTriggerConfig() {
        return $this->_idTriggerConfig;
    }

    /**
     * Set idCompany, value is casted to int
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_TriggerConfig
     */
    public function setIdCompany($mValue) {
        $this->_idCompany = (int) $mValue;
        return $this;
    }

    /**
     * Returns idCompany
     *
     * @return int idCompany
     */
    public function getIdCompany() {
        return $this->_idCompany;
    }

    /**
     * Set idCrawlerType, value is casted to int
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_TriggerConfig
     */
    public function setIdCrawlerType($mValue) {
        $this->_idCrawlerType = (int) $mValue;
        return $this;
    }

    /**
     * Returns idCrawlerType
     *
     * @return int idCrawlerType
     */
    public function getIdCrawlerType() {
        return $this->_idCrawlerType;
    }

    /**
     * Set idTriggerType, value is casted to int
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_TriggerConfig
     */
    public function setIdTriggerType($mValue) {
        $this->_idTriggerType = (int) $mValue;
        return $this;
    }

    /**
     * Returns idTriggerType
     *
     * @return int idTriggerType
     */
    public function getIdTriggerType() {
        return $this->_idTriggerType;
    }

    /**
     * Set patternFileName, value is casted to string
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_TriggerConfig
     */
    public function setPatternFileName($mValue) {
        $this->_patternFileName = (string) $mValue;
        return $this;
    }

    /**
     * Returns patternFileName
     *
     * @return string patternFileName
     */
    public function getPatternFileName() {
        return $this->_patternFileName;
    }

    /**
     * 
     * @param string $strValue
     * @return Marktjagd_Database_Entity_TriggerConfig
     */
    public function setIdCrawlerConfig($strValue) {
        $this->_idCrawlerConfig = $strValue;
        return $this;
    }

    /**
     * 
     * @return int
     */
    public function getIdCrawlerConfig() {
        return $this->_idCrawlerConfig;
    }

    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_TriggerConfig
     */
    public function getMapper() {
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
    public function save($bNull = false) {
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
    public function find($mId) {
        return $this->getMapper()->find($mId, $this);
    }

}
