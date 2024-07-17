
<?php

class Marktjagd_Database_Entity_AmountProducts extends Marktjagd_Database_Entity_Abstract {

    protected $_idAmountProducts;
    protected $_idCompany;
    protected $_amountProducts;
    protected $_startDate;
    protected $_endDate;
    protected $_lastTimeModified;
    protected $_lastTimeChecked;
    protected $_lastImport;
    
    protected $_aColumnMap = array(
        'idAmountProducts' => 'IdAmountProducts',
        'idCompany' => 'IdCompany',
        'amountProducts' => 'AmountProducts',
        'startDate' => 'StartDate',
        'endDate' => 'EndDate',
        'lastImport' => 'LastImport',
        'lastTimeModified' => 'LastTimeModified',
        'lastTimeChecked' => 'LastTimeChecked',
    );

    public function getIdAmountProducts() {
        return $this->_idAmountProducts;
    }

    public function getIdCompany() {
        return $this->_idCompany;
    }

    public function getAmountProducts() {
        return $this->_amountProducts;
    }

    public function getStartDate() {
        return $this->_startDate;
    }

    public function getEndDate() {
        return $this->_endDate;
    }

    public function getLastTimeModified() {
        return $this->_lastTimeModified;
    }

    public function getLastTimeChecked() {
        return $this->_lastTimeChecked;
    }
    
    public function getLastImport() {
        return $this->_lastImport;
    }

    public function setIdAmountProducts($idAmountProducts) {
        $this->_idAmountProducts = $idAmountProducts;
        return $this;
    }

    public function setIdCompany($idCompany) {
        $this->_idCompany = $idCompany;
        return $this;
    }

    public function setAmountProducts($amountProducts) {
        $this->_amountProducts = $amountProducts;
        return $this;
    }

    public function setStartDate($startDate) {
        $this->_startDate = $startDate;
        return $this;
    }

    public function setEndDate($endDate) {
        $this->_endDate = $endDate;
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
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_AmountProducts
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
