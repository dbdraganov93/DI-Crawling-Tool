<?php

/*
 * Class Marktjagd_Database_Mapper_QualityCheckErrors
 */

class Marktjagd_Database_Mapper_QualityCheckErrors extends Marktjagd_Database_Mapper_Abstract {

    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_QualityCheckErrors
     */
    public function getDbTable() {
        return parent::getDbTable();
    }

    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_QualityCheckErrors $oQualityCheckErrors Object data
     * @param bool $bNull Save also null values
     *
     * @return int|mixed
     */
    public function save(Marktjagd_Database_Entity_QualityCheckErrors $oQualityCheckErrors, $bNull = false) {
        return parent::_save($oQualityCheckErrors, $bNull);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_QualityCheckErrors  $oQualityCheckErrors Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_QualityCheckErrors $oQualityCheckErrors) {
        return parent::_find($mId, $oQualityCheckErrors);
    }

    /**
     * 
     * @param string $idCompany
     * @param Marktjagd_Database_Collection_QualityCheckErrors $oQualityCheckErrors
     * @return boolean
     */
    public function findByCompanyId($idCompany, Marktjagd_Database_Collection_QualityCheckErrors $oQualityCheckErrors) {
        $result = $this->getDbTable()->findByCompanyId($idCompany);
        
        if (count($result) > 0) {
            $oQualityCheckErrors->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * 
     * @param string $idCompany
     * @param string $type
     * @param Marktjagd_Database_Collection_QualityCheckErrors $oQualityCheckErrors
     * @return boolean
     */
    public function findByCompanyIdAndType($idCompany, $type, Marktjagd_Database_Entity_QualityCheckErrors $oQualityCheckErrors) {
        $result = $this->getDbTable()->findByCompanyIdAndType($idCompany, $type);
        
        if (count($result) > 0) {
            $oQualityCheckErrors->setOptions($result);
            return true;
        }

        return false;
    }
    
    /**
     * @param string $startTime
     * @param string $endTime
     * @param Marktjagd_Database_Collection_QualityCheckErrors $oQualityCheckErrors
     * @return boolean
     */
    public function findLatestQualityCheckErrorsAdditions($startTime, $endTime, Marktjagd_Database_Collection_QualityCheckErrors $oQualityCheckErrors) {
        $result = $this->getDbTable()->findLatestQualityCheckErrorsAdditions($startTime, $endTime);
        
        if (count($result) > 0) {
            $oQualityCheckErrors->setOptions($result);
            return true;
        }

        return false;
    }
}
