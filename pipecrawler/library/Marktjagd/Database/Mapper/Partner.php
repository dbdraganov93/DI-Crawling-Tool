<?php

class Marktjagd_Database_Mapper_Partner extends Marktjagd_Database_Mapper_Abstract
{
    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_Partner
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }

    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_Partner $oPartner
     * @param bool $bNull Save also null values
     * @param bool $bForceInsert Force insert
     * @return void
     */
    public function save(Marktjagd_Database_Entity_Partner $oPartner, $bNull = false, $bForceInsert = false)
    {
        parent::_save($oPartner, $bNull, $bForceInsert);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_Partner $oPartner
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_Partner $oPartner)
    {
        return parent::_find($mId, $oPartner);
    }

    /**
     *
     * @param string $companyId
     * @param Marktjagd_Database_Entity_Partner $oPartner
     * @return boolean
     */
    public function findByCompanyId($companyId, Marktjagd_Database_Entity_Partner $oPartner) {
        $result = $this->getDbTable()->findByCompanyId($companyId);

        if ($result) {
            $oPartner->setOptions($result);
            return true;
        }

        return false;
    }
}