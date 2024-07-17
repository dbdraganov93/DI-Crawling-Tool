<?php

/**
 * Class Marktjagd_Database_DbTable_Abstract
 */
abstract class Marktjagd_Database_DbTable_Abstract extends Zend_Db_Table_Abstract
{
    /**
     * Use own select object to add table prefix by columns.
     *
     * @param bool $withFromPart Whether or not to include the from part of the select
     * based on the table
     * @return Marktjagd_Database_DbTable_Select
     */
    public function select($withFromPart = self::SELECT_WITHOUT_FROM_PART)
    {
        $select = new Marktjagd_Database_DbTable_Select($this);

        if ($withFromPart == self::SELECT_WITH_FROM_PART) {
            $select->from($this->info(self::NAME), Zend_Db_Table_Select::SQL_WILDCARD, $this->info(self::SCHEMA));
        }

        return $select;
    }

    /**
     * Sets the database depending on the loaded module
     * @see Zend_Db_Table_Abstract::_setupDatabaseAdapter()
     */
    protected function _setupDatabaseAdapter()
    {
        $module = Zend_Controller_Front::getInstance()->getRequest()->getModuleName();
        $resource = Zend_Controller_Front::getInstance()->getParam('bootstrap')
                                                        ->getPluginResource('multidb');
        $db = 'db1';

        if ($module == 'Ftp') {
            $db = 'db2';
        }

        $this->_db = $resource->getDb($db);
        parent::_setupDatabaseAdapter();
    }
}