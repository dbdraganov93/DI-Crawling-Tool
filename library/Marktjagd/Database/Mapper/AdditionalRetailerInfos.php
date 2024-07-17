<?php

class Marktjagd_Database_Mapper_AdditionalRetailerInfos extends Marktjagd_Database_Mapper_Abstract
{

    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_AdditionalRetailerInfos
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }

    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_AdditionalRetailerInfos $oAdditionalRetailerInfos Object data
     * @param bool $bNull Save also null values
     * @param bool $bForceInsert Force insert
     * @return void
     */
    public function save(Marktjagd_Database_Entity_AdditionalRetailerInfos $oAdditionalRetailerInfos, $bNull = false, $bForceInsert = false)
    {
        parent::_save($oAdditionalRetailerInfos, $bNull, $bForceInsert);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_AdditionalRetailerInfos  $oAdditionalRetailerInfos Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_AdditionalRetailerInfos $oAdditionalRetailerInfos)
    {
        return parent::_find($mId, $oAdditionalRetailerInfos);
    }
        
    /**
     * 
     * @param string $idCompany
     * @param Marktjagd_Database_Collection_AdditionalRetailerInfos $oAdditionalRetailerInfos
     * @return boolean
     */
    public function findAllStoreInfosByCompanyId($idCompany, Marktjagd_Database_Collection_AdditionalRetailerInfos $oAdditionalRetailerInfos) {
        $result = $this->getDbTable()->findAllStoreInfosByCompanyId($idCompany);
        if (count($result) > 0) {
            $oAdditionalRetailerInfos->setOptions($result);
            return true;
        }
        return false;
    }
    
    /**
     *
     * @param mixed $storeId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_AdditionalRetailerInfos  $oAdditionalRetailerInfos Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function deleteByStoreId($storeId, Marktjagd_Database_Entity_AdditionalRetailerInfos $oAdditionalRetailerInfos)
    {
        $result = $this->getDbTable()->deleteByStoreId($storeId);
        if (count($result) > 0) {
            $oAdditionalRetailerInfos->setOptions($result);
            return true;
        }
        return false;
    }
}