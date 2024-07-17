<?php

class Marktjagd_Database_Entity_CrawlerLogType extends Marktjagd_Database_Entity_Abstract
{
    // table fields
    protected $_idCrawlerLogType;
    protected $_logType;

    /**
     * Contains mapping of table columns to function
     *
     * @var array
     */
    protected $_aColumnMap = array('idCrawlerLogType' => 'IdCrawlerLogType',
                                   'logType' => 'LogType');


    /**
     * Set idCrawlerLogType, value is casted to int
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_CrawlerLogType
     */
    public function setIdCrawlerLogType($mValue)
    {
        $this->_idCrawlerLogType = (int) $mValue;
        return $this;
    }

    /**
     * Returns idCrawlerLogType
     *
     * @return int idCrawlerLogType
     */
    public function getIdCrawlerLogType()
    {
        return $this->_idCrawlerLogType;
    }

    /**
     * Set type, value is casted to string
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_CrawlerLogType
     */
    public function setLogType($mValue)
    {
        $this->_logType = (string) $mValue;
        return $this;
    }

    /**
     * Returns type
     *
     * @return string type
     */
    public function getLogType()
    {
        return $this->_logType;
    }

    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_CrawlerLogType
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