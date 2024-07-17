<?php

/**
 * Class Marktjagd_Database_Entity_User
 */
class Marktjagd_Database_Entity_User extends Marktjagd_Database_Entity_Abstract
{
    // table fields
    /* @var int */
    protected $_idUser;

    /* @var string */
    protected $_userName;

    /* @var string */
    protected $_password;

    /* @var string */
    protected $_passwordSalt;

    /* @var string */
    protected $_realName;

    /* @var int */
    protected $_idRole;
    
    /* @var int */
    protected $_idUserRedmine;

    /**
     * Contains mapping of table columns to function
     *
     * @var array
     */
    protected $_aColumnMap = array('idUser' => 'IdUser',
                                   'userName' => 'UserName',
                                   'password' => 'Password',
                                   'passwordSalt' => 'PasswordSalt',
                                   'realName' => 'RealName',
                                   'idRole' => 'IdRole',
                                   'idUserRedmine' => 'IdUserRedmine'
    );

    /**
     * Relationship map
     *
     * @var array
     */
    protected $_aRelationMap = array(
        'Role' => 'Marktjagd_Database_Entity_Role'
    );

    /**
     * Relation property map
     *
     * @var array
     */
    protected $_aRelationPropertyMap = array(
        'Role' => 'Role'
    );

    /**
     * Relationship object for table Role
     *
     * @var Marktjagd_Database_Entity_Role
     */
    protected $_oRole;

    /**
     * @return int
     */
    public function getIdUser() {
        return $this->_idUser;
    }

    /**
     * @param int $idUser
     *
     * @return Marktjagd_Database_Entity_User
     */
    public function setIdUser( $idUser ) {
        $this->_idUser = $idUser;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword() {
        return $this->_password;
    }

    /**
     * @param string $password
     *
     * @return Marktjagd_Database_Entity_User
     */
    public function setPassword( $password ) {
        $this->_password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getPasswordSalt() {
        return $this->_passwordSalt;
    }

    /**
     * @param string $passwordSalt
     *
     * @return Marktjagd_Database_Entity_User
     */
    public function setPasswordSalt( $passwordSalt ) {
        $this->_passwordSalt = $passwordSalt;

        return $this;
    }

    /**
     * @return string
     */
    public function getRealName() {
        return $this->_realName;
    }

    /**
     * @param string $realName
     *
     * @return Marktjagd_Database_Entity_User
     */
    public function setRealName( $realName ) {
        $this->_realName = $realName;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserName() {
        return $this->_userName;
    }

    /**
     * @param string $userName
     *
     * @return Marktjagd_Database_Entity_User
     */
    public function setUserName( $userName ) {
        $this->_userName = $userName;

        return $this;
    }

    /**
     * @return int
     */
    public function getIdRole()
    {
        return $this->_idRole;
    }

    /**
     * @param int $idRole
     *
     * @return Marktjagd_Database_Entity_User
     */
    public function setIdRole($idRole)
    {
        $this->_idRole = $idRole;

        return $this;
    }
    
    /**
     * @return int
     */
    public function getIdUserRedmine()
    {
        return $this->_idUserRedmine;
    }

    /**
     * @param int $idUserRedmine
     *
     * @return Marktjagd_Database_Entity_User
     */
    public function setIdUserRedmine($idUserRedmine)
    {
        $this->_idUserRedmine = $idUserRedmine;

        return $this;
    }

    /**
     * @return Marktjagd_Database_Entity_Role
     */
    public function getRole()
    {
        return $this->_oRole;
    }

    /**
     * @param Marktjagd_Database_Entity_Role $oRole
     *
     * @return Marktjagd_Database_Entity_User
     */
    public function setRole($oRole)
    {
        $this->_oRole = $oRole;

        return $this;
    }

    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_User
     */
    public function getMapper()
    {
        return parent::getMapper();
    }

    /**
     * Saves data to database If the primary key is set,
     * data will be updated.
     *
     * @param bool $bNull Save also null values
     *
     * @return int|bool
     */
    public function save($bNull = false)
    {
        return $this->getMapper()->save($this, $bNull);
    }

    /**
     * Loads the data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId)
    {
        return $this->getMapper()->find($mId, $this);
    }
}