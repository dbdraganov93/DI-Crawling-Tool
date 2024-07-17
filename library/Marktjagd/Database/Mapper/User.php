<?php

/**
 * Mapperklasse für die GUI-User in der DB
 *
 * Class Marktjagd_Database_Mapper_User
 */
class Marktjagd_Database_Mapper_User extends Marktjagd_Database_Mapper_Abstract
{
    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_User
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }

    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_User $oUser Object data
     * @param bool $bNull Save also null values
     *
     * @return int|bool
     */
    public function save(Marktjagd_Database_Entity_User $oUser, $bNull = false)
    {
        return parent::_save($oUser, $bNull);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_User $oUser Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_User $oUser)
    {
        return parent::_find($mId, $oUser);
    }

    /**
     * @param $userName
     * @param Marktjagd_Database_Entity_User $oUser
     *
     * @return bool
     */
    public function findByUserName($userName, Marktjagd_Database_Entity_User $oUser)
    {
        $result = $this->getDbTable()->findByUserName($userName);

        if ($result) {
            $oUser->setOptions($result);
            return true;
        }

        return false;
    }
    
    public function findByIdRedmine($idRedmine, Marktjagd_Database_Entity_User $oUser)
    {
        $result = $this->getDbTable()->findByIdRedmine($idRedmine);

        if ($result) {
            $oUser->setOptions($result);
            return true;
        }

        return false;
    }
    
    /**
     * @param Marktjagd_Database_Collection_User $oUser
     *
     * @return bool
     */
    public function findAll(Marktjagd_Database_Collection_User $oUser)
    {
        $result = $this->getDbTable()->findAll();
                
        if (count($result)) {
            $oUser->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * @param Marktjagd_Database_Entity_User $oUser
     *
     * @return mixed
     */
    public function insert(Marktjagd_Database_Entity_User $oUser)
    {
        return $this->getDbTable()->insert($oUser->toArray(true));
    }
}