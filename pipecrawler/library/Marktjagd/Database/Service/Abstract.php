<?php

abstract class Marktjagd_Database_Service_Abstract
{
    /**
     * @param string | array $where
     * @param Marktjagd_Database_Collection_Abstract $collection
     * @return \Marktjagd_Database_Collection_Abstract
     */
    public function fetchAll($where, $collection)
    {
        $collection->getMapper()->fetchAll($where, $collection);
        return $collection;
    }
}