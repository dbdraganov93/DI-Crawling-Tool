<?php

/**
 * Mapperklasse für DB-Objekt TriggerConfig
 *
 * Class Marktjagd_Database_Mapper_TriggerConfig
 */
class Marktjagd_Database_Mapper_TriggerConfig extends Marktjagd_Database_Mapper_Abstract {

    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_TriggerConfig
     */
    public function getDbTable() {
        return parent::getDbTable();
    }

    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_TriggerConfig  $oTriggerConfig Object data
     * @param bool $bNull Save also null values
     *
     * @return void
     */
    public function save(Marktjagd_Database_Entity_TriggerConfig $oTriggerConfig, $bNull = false) {
        parent::_save($oTriggerConfig, $bNull);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_TriggerConfig  $oTriggerConfig Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_TriggerConfig $oTriggerConfig) {
        return parent::_find($mId, $oTriggerConfig);
    }

    /**
     * Ermittelt die Triggerkonfiguration für ein Unternehmen und den entsprechenden Importtyp
     *
     * @param $companyId
     * @param $triggerType
     * @param Marktjagd_Database_Collection_TriggerConfig $oTriggerConfig
     * @return bool
     */
    public function findByCompanyTriggerType($companyId, $triggerType, $oTriggerConfig) {
        $result = $this->getDbTable()->findByCompanyTriggerType($companyId, $triggerType);

        if (count($result) > 0) {
            $oTriggerConfig->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Ermittelt die Triggerkonfiguration anhand der entsprechenden Triggerart
     *
     * @param $triggerType
     * @param $oTriggerConfig
     * @return bool
     */
    public function findByTriggerType($triggerType, $oTriggerConfig) {
        $result = $this->getDbTable()->findByTriggerType($triggerType);

        if (count($result) > 0) {
            $oTriggerConfig->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Ermittelt die Triggerkonfiguration für ein Unternehmen anhand der entsprechenden Triggerart und des Crawlertyps
     *
     * @param $companyId
     * @param $triggerType
     * @param $crawlerType
     * @param Marktjagd_Database_Entity_TriggerConfig $oTriggerConfig
     * @return bool
     */
    public function findByCompanyTriggerTypeCrawlerType($companyId, $triggerType, $crawlerType, $oTriggerConfig) {
        $result = $this->getDbTable()->findByCompanyTriggerTypeCrawlerType($companyId, $triggerType, $crawlerType);

        if ($result) {
            $oTriggerConfig->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Ermittelt die Triggerkonfiguration für ein Unternehmen anhand der entsprechenden CrawlerConfig-ID
     * 
     * @param type $crawlerConfig
     * @param Marktjagd_Database_Entity_TriggerConfig $oTriggerConfig
     */
    public function findByCrawlerConfigId($crawlerConfig, $oTriggerConfig) {
        $result = $this->getDbTable()->findByCrawlerConfigId($crawlerConfig);

        if ($result) {
            $oTriggerConfig->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Entfernt die Triggerkonfiguration anhand der TriggerConfig-Id
     *
     * @param int $idTriggerConfig
     *
     * @return bool
     */
    public function deleteByTriggerConfigId($idTriggerConfig) {
        if ($idTriggerConfig != '') {
            if ($this->getDbTable()->delete('TriggerConfig.idTriggerConfig ="' . $idTriggerConfig . '"')) {
                return true;
            }
        }

        return false;
    }

}
