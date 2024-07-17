<?php

class Marktjagd_Database_Database
{
    /**
     * Creates a database object.
     *
     * @param mixed	$mDatabase Name or object (Marktjagd_Database_Mapper_Abstract)
     * @throws Marktjagd_Database_Exception
     *
     * @return Zend_Db_Table_Abstract
     */
    public static function factory($mDatabase)
    {
        // get database name by class name
        if ($mDatabase instanceof Marktjagd_Database_Mapper_Abstract) {
            $mDatabase = str_replace('Mapper', 'DbTable', get_class($mDatabase));
        } elseif (is_string($mDatabase)) {
            $mDatabase = 'Marktjagd_Database_DbTable_' . $mDatabase;
        }

        if (!is_string($mDatabase)) {
            throw new Marktjagd_Database_Exception('Invalid table data gateway provided');
        }

        return new $mDatabase();
    }
}