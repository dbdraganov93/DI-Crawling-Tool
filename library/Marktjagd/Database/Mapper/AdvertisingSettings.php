<?php

class Marktjagd_Database_Mapper_AdvertisingSettings extends Marktjagd_Database_Mapper_Abstract
{

    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_AdvertisingSettings
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }

    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_AdvertisingSettings $oAdvertisingSettings Object data
     * @param bool $bNull Save also null values
     * @param bool $bForceInsert Force insert
     * @return void
     */
    public function save(Marktjagd_Database_Entity_AdvertisingSettings $oAdvertisingSettings, $bNull = false, $bForceInsert = false)
    {
        parent::_save($oAdvertisingSettings, $bNull, $bForceInsert);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_AdvertisingSettings  $oAdvertisingSettings Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_AdvertisingSettings $oAdvertisingSettings)
    {
        return parent::_find($mId, $oAdvertisingSettings);
    }
    
    /**
     * 
     * @param type $companyId
     * @param type $oAdvertisingSettings
     * @return boolean
     */
    public function findFutureAdsByCompanyId($companyId, $startDate, $oAdvertisingSettings) {
        $result = $this->getDbTable()->findFutureAdsByCompanyId($companyId, $startDate);
        if (count($result) > 0) {
            $oAdvertisingSettings->setOptions($result);
            return true;
        }
        return false;
    }
    
    /**
     * 
     * @param type $adId
     * @return boolean
     */
    public function deleteAdvertisingSetting($adId) {
        $result = $this->getDbTable()->deleteAdvertisingSetting($adId);
        return true;
    }
    
    /**
     * 
     * @param type $companyId
     * @param type $oAdvertisingSettings
     * @return boolean
     */
    public function findAdsByCompanyId($companyId, $oAdvertisingSettings) {
        $result = $this->getDbTable()->findAdsByCompanyId($companyId);
        if (count($result) > 0) {
            $oAdvertisingSettings->setOptions($result);
            return true;
        }
        return false;
    }
    
    public function findActualAdsByCompanyId($companyId, $oAdvertisingSettings) {
        $result = $this->getDbTable()->findActualAdsByCompanyId($companyId);
        if (count($result) > 0) {
            $oAdvertisingSettings->setOptions($result);
            return true;
        }
        return false;
    }
    
    public function findSingleAd($adId, $oAd) {
        $result = $this->getDbTable()->findSingleAd($adId);
        if (count($result) > 0) {
            $oAd->setOptions($result);
            return true;
        }
        return false;
    }

}
