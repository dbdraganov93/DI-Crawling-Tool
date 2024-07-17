<?php

class Marktjagd_Database_Entity_AmountStores extends Marktjagd_Database_Entity_Abstract {

    protected $_idAmountStores;
    protected $_idCompany;
    protected $_amountStores;
    protected $_lastTimeModified;
    protected $_lastTimeChecked;
    protected $_lastImport;
    
    protected $_aColumnMap = array(
        'idAmountStores' => 'IdAmountStores',
        'idCompany' => 'IdCompany',
        'amountStores' => 'AmountStores',
        'lastImport' => 'LastImport',
        'lastTimeModified' => 'LastTimeModified',
        'lastTimeChecked' => 'LastTimeChecked'
    );
    
    protected $_aRelationMap = array('Company' => 'Marktjagd_Database_Entity_Company');
    
    protected $_aRelationPropertyMap = array('Company' => 'Company');

    /**
     * Relationship object for table Company
     *
     * @var Marktjagd_Database_Entity_Company
     */
    protected $_oCompany;
    
    public function getCompany() {
        return $this->_oCompany;
    }
    
    public function getIdAmountStores() {
        return $this->_idAmountStores;
    }

    public function getIdCompany() {
        return $this->_idCompany;
    }

    public function getAmountStores() {
        return $this->_amountStores;
    }

    public function getLastTimeModified() {
        return $this->_lastTimeModified;
    }

    public function getLastImport() {
        return $this->_lastImport;
    }
    
    public function getLastTimeChecked() {
        return $this->_lastTimeChecked;
    }

    public function setIdAmountStores($idAmountStores) {
        $this->_idAmountStores = $idAmountStores;
        return $this;
    }

    public function setIdCompany($idCompany) {
        $this->_idCompany = $idCompany;
        return $this;
    }

    public function setAmountStores($amountStores) {
        $this->_amountStores = $amountStores;
        return $this;
    }

    public function setLastTimeModified($lastTimeModified) {
        $this->_lastTimeModified = $lastTimeModified;
        return $this;
    }

    public function setLastTimeChecked($lastTimeChecked) {
        $this->_lastTimeChecked = $lastTimeChecked;
        return $this;
    }
    
    public function setLastImport($lastImport) {
        $this->_lastImport = $lastImport;
        return $this;
    }

    /**
     * Set entity company
     *
     * @param Marktjagd_Database_Entity_Company         $oCompany
     *
     * @return Marktjagd_Database_Entity_AmountStores
     */
    public function setCompany(Marktjagd_Database_Entity_Company $oCompany) {
        $this->_oCompany = $oCompany;
        return $this;
    }
    
    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_AmountStores
     */
    public function getMapper() {
        return parent::getMapper();
    }

    /**
     * Saves data to database If the primary key is set,
     * data will be updated.
     *
     * @param bool $bNull Save also null values
     * @param bool $bForceInsert
     * @return void
     */
    public function save($bNull = false, $bForceInsert = false) {
        $this->getMapper()->save($this, $bNull, $bForceInsert);
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
