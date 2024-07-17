<?php

class Marktjagd_Database_Entity_Redmine extends Marktjagd_Database_Entity_Abstract
{
    // table fields
    protected $_idCompany;
    protected $_idRedmine;

    /**
     * Contains mapping of table columns to function
     *
     * @var array
     */
    protected $_aColumnMap = array('idCompany' => 'IdCompany',
                                    'idRedmine' => 'IdRedmine');


    /**
     * Set idRedmine, value is casted to int
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_Redmine
     */
    public function setIdRedmine($mValue)
    {
        $this->_idRedmine = (int) $mValue;
        return $this;
    }

    /**
     * Returns idRedmine
     *
     * @return int idRedmine
     */
    public function getIdRedmine()
    {
        return $this->_idRedmine;
    }

    /**
     * Set lastName, value is casted to string
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_Redmine
     */
    public function setIdCompany($mValue)
    {
        $this->_idCompany = (int) $mValue;
        return $this;
    }

    /**
     * Returns lastName
     *
     * @return string lastName
     */
    public function getIdCompany()
    {
        return $this->_idCompany;
    }

    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_Redmine
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
    public function save($bNull = false, $forceInsert = false)
    {
        $this->getMapper()->save($this, $bNull, $forceInsert);
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