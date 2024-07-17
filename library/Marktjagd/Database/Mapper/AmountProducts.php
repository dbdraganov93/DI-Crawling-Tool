<?php

class Marktjagd_Database_Mapper_AmountProducts extends Marktjagd_Database_Mapper_Abstract {
     
    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_AmountProducts
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }
    
    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_AmountProducts $oAmountProducts Object data
     * @param bool $bNull Save also null values
     * @param bool $bForceInsert Force insert
     * @return void
     */
    public function save(Marktjagd_Database_Entity_AmountProducts $oAmountProducts, $bNull = false, $bForceInsert = false)
    {
        parent::_save($oAmountProducts, $bNull, $bForceInsert);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_AmountProducts  $oAmountProducts Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_AmountProducts $oAmountProducts)
    {
        return parent::_find($mId, $oAmountProducts);
    }
    
    public function findLatestState($companyId, Marktjagd_Database_Entity_AmountProducts $oProducts) {
        $result = $this->getDbTable()->findLatestState($companyId);
        
        if (count($result) > 0) {
            $oProducts->setOptions($result);
            return true;
        }

        return false;
    }
    
    public function findByCompanyIdAndTime($companyId, $startDate, $endDate, Marktjagd_Database_Collection_AmountProducts $oProducts) {
        $result = $this->getDbTable()->findByCompanyIdAndTime($companyId, $startDate, $endDate);

        if (count($result) > 0) {
            $oProducts->setOptions($result);
            return true;
        }

        return false;
    }
}