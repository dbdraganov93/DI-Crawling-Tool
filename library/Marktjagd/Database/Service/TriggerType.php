<?php

/**
 * Service für DB-Abfragen zur Triggertypen
 *
 * Class Marktjagd_Database_Service_TriggerType
 */
class Marktjagd_Database_Service_TriggerType extends Marktjagd_Database_Service_Abstract
{
    /**
     * Ermittelt die Triggertypen anhand des Namens
     *
     * @param $name
     * @return Marktjagd_Database_Entity_TriggerType
     */
    public function findByName($name)
    {
        $eTriggerType = new Marktjagd_Database_Entity_TriggerType();

        $mTriggerType = new Marktjagd_Database_Mapper_TriggerType();
        $mTriggerType->findByName($name, $eTriggerType);

        return $eTriggerType ;
    }

    /**
     * Ermittelt alle Triggertypen
     *
     * @return Marktjagd_Database_Collection_TriggerType
     */
    public function findAll()
    {
        $cTriggerType = new Marktjagd_Database_Collection_TriggerType();
        $mTriggerType = new Marktjagd_Database_Mapper_TriggerType();
        $mTriggerType->fetchAll(null, $cTriggerType);

        return $cTriggerType;
    }
}