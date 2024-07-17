<?php

class Marktjagd_Database_Mapper_AmountStores extends Marktjagd_Database_Mapper_Abstract {
     
    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_AmountStores
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }
    
    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_AmountStores $oAmountStores Object data
     * @param bool $bNull Save also null values
     * @param bool $bForceInsert Force insert
     * @return void
     */
    public function save(Marktjagd_Database_Entity_AmountStores $oAmountStores, $bNull = false, $bForceInsert = false)
    {
        parent::_save($oAmountStores, $bNull, $bForceInsert);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_AmountStores  $oAmountStores Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_AmountStores $oAmountStores)
    {
        return parent::_find($mId, $oAmountStores);
    }
    
    /**
     * 
     * @param type $companyId
     * @param Marktjagd_Database_Entity_AmountStores $oStores
     * @return boolean
     */
    public function findByCompanyId($companyId, Marktjagd_Database_Entity_AmountStores $oStores) {
        $result = $this->getDbTable()->findByCompanyId($companyId);
        if (count($result) > 0) {
            $oStores->setOptions($result);
            return true;
        }

        return false;
    }
    
    public function findByCompanyIdAndTime($companyId, $startDate, $endDate, Marktjagd_Database_Collection_AmountStores $oStores) {
        $result = $this->getDbTable()->findByCompanyIdAndTime($companyId, $startDate, $endDate);

        if (count($result) > 0) {
            $oStores->setOptions($result);
            return true;
        }

        return false;
    }
    
    public function findLatestState(Marktjagd_Database_Collection_AmountStores $oStores) {
        $result = $this->getDbTable()->findLatestState();
        
        if (count($result) > 0) {
            $oStores->setOptions($result);
            return true;
        }

        return false;
    }
}