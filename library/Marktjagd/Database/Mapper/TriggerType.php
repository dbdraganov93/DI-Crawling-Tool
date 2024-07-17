<?php

class Marktjagd_Database_Mapper_TriggerType extends Marktjagd_Database_Mapper_Abstract
{
    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_TriggerType
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }

    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_TriggerType  $oTriggerType Object data
     * @param bool $bNull Save also null values
     *
     * @return void
     */
    public function save(Marktjagd_Database_Entity_TriggerType $oTriggerType, $bNull = false)
    {
        parent::_save($oTriggerType, $bNull);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_TriggerType  $oTriggerType Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_TriggerType $oTriggerType)
    {
        return parent::_find($mId, $oTriggerType);
    }

    /**
     * Ermittelt die Triggertypen anhand des Namens
     *
     * @param $name
     * @param Marktjagd_Database_Entity_TriggerType $oTriggerType
     * @return bool
     */
    public function findByName($name, $oTriggerType)
    {
        $result = $this->getDbTable()->findByName($name);

        if ($result) {
            $oTriggerType->setOptions($result);
            return true;
        }

        return false;
    }
}