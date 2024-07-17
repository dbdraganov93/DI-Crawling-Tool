<?php

class Marktjagd_Database_Mapper_CrawlerBehaviour extends Marktjagd_Database_Mapper_Abstract
{
    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_CrawlerBehaviour
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }

    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_CrawlerBehaviour  $oCrawlerBehaviour Object data
     * @param bool $bNull Save also null values
     *
     * @return void
     */
    public function save(Marktjagd_Database_Entity_CrawlerBehaviour $oCrawlerBehaviour, $bNull = false)
    {
        parent::_save($oCrawlerBehaviour, $bNull);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_CrawlerBehaviour  $oCrawlerBehaviour Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_CrawlerBehaviour $oCrawlerBehaviour)
    {
        return parent::_find($mId, $oCrawlerBehaviour);
    }
}