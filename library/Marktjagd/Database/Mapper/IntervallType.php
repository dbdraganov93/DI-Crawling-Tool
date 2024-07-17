<?php

class Marktjagd_Database_Mapper_IntervallType extends Marktjagd_Database_Mapper_Abstract {

    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_IntervallType
     */
    public function getDbTable() {
        return parent::getDbTable();
    }

    /**
     * 
     * @param string $intervallId
     * @param Marktjagd_Database_Collection_IntervallType $oIntervallType
     * @return boolean
     */
    public function findIntervallTypeById($intervallId, $oIntervallType) {
        $result = $this->getDbTable()->findIntervallTypeById($intervallId);
        if (count($result) > 0) {
            $oIntervallType->setOptions($result);
            return true;
        }
        return false;
    }
}
