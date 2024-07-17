<?php

class Marktjagd_Database_Mapper_Redmine extends Marktjagd_Database_Mapper_Abstract {
     
    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_Redmine
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }
    
    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_Redmine $oRedmine Object data
     * @param bool $bNull Save also null values
     * @param bool $bForceInsert Force insert
     * @return void
     */
    public function save(Marktjagd_Database_Entity_Redmine $oRedmine, $bNull = false, $bForceInsert = false)
    {
        parent::_save($oRedmine, $bNull, $bForceInsert);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_Redmine  $oRedmine Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_Redmine $oRedmine)
    {
        return parent::_find($mId, $oRedmine);
    }
    
    /**
     * Findet Company-ID anhand der Redmine-ID
     * 
     * @param Marktjagd_Database_Entity_Redmine $redmineId
     */
    public function findByRedmineId($redmineId, Marktjagd_Database_Entity_Redmine $oRedmine) {
        $result = $this->getDbTable()->findByRedmineId($redmineId);
        
        if (count($result) > 0) {
            $oRedmine->setOptions($result);
            return true;
        }

        return false;
        
    }
}