<?php

class Marktjagd_Database_Entity
{
    /**
     * Creates a entity object.
     *
     * @param mixed	$mEntity Name or object (Marktjagd_Database_Collection_Abstract, Marktjagd_Database_Mapper_Abstract)
     * @param array|Zend_Db_Table_Row $mOptions Data
     * @throws Model_Exception
     *
     * @return Marktjagd_Database_Entity_Abstract
     */
    public static function factory($mEntity, $mOptions = null)
    {
        // get entity name by class name
        if ($mEntity instanceof Marktjagd_Database_Mapper_Abstract) {
            $mEntity = str_replace('Mapper', 'Entity', get_class($mEntity));
        } elseif ($mEntity instanceof Marktjagd_Database_Collection_Abstract) {
            $mEntity = str_replace('Collection', 'Entity', get_class($mEntity));
        } elseif (is_string($mEntity)) {
            $mEntity = 'Marktjagd_Database_Entity_' . $mEntity;
        }

        if (!is_string($mEntity)) {
            throw new Marktjagd_Database_Exception('Invalid entity provided.');
        }

        return new $mEntity($mOptions);
    }
}