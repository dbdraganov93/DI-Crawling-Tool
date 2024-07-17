<?php

/*
 * Class Marktjagd_Database_Entity_QualityCheckErrors
 */

class Marktjagd_Database_Entity_QualityCheckErrors extends Marktjagd_Database_Entity_Abstract {

    protected $_idQualityCheckErrors;
    protected $_idCompany;
    protected $_type;
    protected $_actualAmount;
    protected $_lastAmount;
    protected $_lastTimeModified;
    protected $_lastImport;
    protected $_status;
    protected $_oCompany;
    protected $_idUser;
    protected $_timeAdded;
    protected $_errorStatus;
    protected $_oUser;
    
    protected $_aColumnMap = array(
        'idQualityCheckErrors' => 'IdQualityCheckErrors',
        'idCompany' => 'IdCompany',
        'type' => 'Type',
        'actualAmount' => 'ActualAmount',
        'lastAmount' => 'LastAmount',
        'lastTimeModified' => 'LastTimeModified',
        'lastImport' => 'LastImport',
        'status' => 'Status',
        'timeAdded' => 'TimeAdded',
        'idUser' => 'IdUser',
        'errorStatus' => 'ErrorStatus'
    );

    protected $_aRelationMap = array('Company' => 'Marktjagd_Database_Entity_Company',
        'User' => 'Marktjagd_Database_Entity_User');
    
    protected $_aRelationPropertyMap = array('Company' => 'Company',
        'User' => 'User');

    /*
     * @return  Marktjagd_Database_Mapper_QualityCheckErrors
     */

    public function getMapper() {
        return parent::getMapper();
    }

    /**
     * @return int
     */
    public function getIdQualityCheckErrors() {
        return $this->_idQualityCheckErrors;
    }

    /**
     * @return int
     */
    public function getIdCompany() {
        return $this->_idCompany;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->_type;
    }

    /**
     * @return int
     */
    public function getActualAmount() {
        return $this->_actualAmount;
    }

    /**
     * @return int
     */
    public function getLastAmount() {
        return $this->_lastAmount;
    }

    /**
     * @return int
     */
    public function getLastTimeModified() {
        return $this->_lastTimeModified;
    }
    
    /**
     * @return int
     */
    public function getLastImport() {
        return $this->_lastImport;
    }
    
    /**
     * @return int
     */
    public function getStatus() {
        return $this->_status;
    }
    
    /**
     * @return string
     */
    public function getTimeAdded() {
        return $this->_timeAdded;
    }
    
    /**
     * @return int
     */
    public function getIdUser() {
        return $this->_idUser;
    }

    /**
     * 
     * @return Marktjagd_Database_Entity_Company
     */
        public function getCompany() {
        return $this->_oCompany;
    }
    
    public function getErrorStatus() {
        return $this->_errorStatus;
    }
    
    /**
     * 
     * @return Marktjagd_Database_Entity_User
     */
        public function getUser() {
        return $this->_oUser;
    }
    /**
     * @param int $idQualityCheckErrors
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setIdQualityCheckErrors($idQualityCheckErrors) {
        $this->_idQualityCheckErrors = $idQualityCheckErrors;
        return $this;
    }

    /**
     * @param int $idCompany
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setIdCompany($idCompany) {
        $this->_idCompany = $idCompany;
        return $this;
    }

    /**
     * @param int $type
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setType($type) {
        $this->_type = $type;
        return $this;
    }

    /**
     * @param int $actualAmount
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setActualAmount($actualAmount) {
        $this->_actualAmount = $actualAmount;
        return $this;
    }

    /**
     * @param int $lastAmount
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setLastAmount($lastAmount) {
        $this->_lastAmount = $lastAmount;
        return $this;
    }

    /**
     * @param type $lastTimeModified
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setLastTimeModified($lastTimeModified) {
        $this->_lastTimeModified = $lastTimeModified;
        return $this;
    }
    
    /**
     * @param type $lastImport
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setLastImport($lastImport) {
        $this->_lastImport = $lastImport;
        return $this;
    }
    
    /**
     * 
     * @param string $timeAdded
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setTimeAdded($timeAdded) {
        $this->_timeAdded = $timeAdded;
        return $this;
    }
    
    /**
     * @param type $status
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setStatus($status) {
        $this->_status = $status;
        return $this;
    }
    
    /**
     * 
     * @param type $idUser
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setIdUser($idUser) {
        $this->_idUser = $idUser;
        return $this;
    }
    
    /**
     * 
     * @param type $errorStatus
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setErrorStatus($errorStatus) {
        $this->_errorStatus = $errorStatus;
        return $this;
    }
    
    public function setUser(Marktjagd_Database_Entity_User $oUser) {
        $this->_oUser = $oUser;
        return $this;
    }
    
    /**
     * @param Marktjagd_Database_Entity_Company $oCompany
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function setCompany(Marktjagd_Database_Entity_Company $oCompany) {
        $this->_oCompany = $oCompany;
        return $this;
    }

    /**
     * Saves data to database If the primary key is set,
     * data will be updated.
     *
     * @param bool $bNull Save also null values
     *
     * @return int|bool
     */
    public function save($bNull = false) {
        return $this->getMapper()->save($this, $bNull);
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
