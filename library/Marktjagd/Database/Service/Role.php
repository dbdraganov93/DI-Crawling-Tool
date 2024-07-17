<?php

/**
 * Services zum Auslesen von GUI-Nutzerrollen aus der Datenbank
 *
 * Class Marktjagd_Database_Service_Role
 */
class Marktjagd_Database_Service_Role extends Marktjagd_Database_Service_Abstract
{
    /**
     * Ermittelt alle vorhandenen Logins für die GUI aus der DB
     *
     * @return Marktjagd_Database_Collection_User
     */
    public function findAll()
    {
        $cRole = new Marktjagd_Database_Collection_Role();
        $mRole = new Marktjagd_Database_Mapper_Role();
        $mRole->fetchAll(null, $cRole);
        return $cRole;
    }
}