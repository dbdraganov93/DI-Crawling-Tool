<?php

/**
 * Class Marktjagd_Database_Entity_Users
 */
class Marktjagd_Database_Entity_Users extends Marktjagd_Database_Entity_Abstract
{
    // table fields
    protected $_login;
    protected $_password;
    protected $_salt;
    protected $_directory;
    protected $_allowed_ips;
    protected $_comment;

    /**
     * Contains mapping of table columns to function
     *
     * @var array
     */
    protected $_aColumnMap = array('login' => 'Login',
                                   'password' => 'Password',
                                   'salt' => 'Salt',
                                   'directory' => 'Directory',
                                   'allowed_ips' => 'AllowedIps',
                                   'comment' => 'Comment');


    /**
     * Set login, value is casted to string
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_Users
     */
    public function setLogin($mValue)
    {
        $this->_login = (string) $mValue;
        return $this;
    }

    /**
     * Returns login
     *
     * @return string login
     */
    public function getLogin()
    {
        return $this->_login;
    }

    /**
     * Set password, value is casted to string
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_Users
     */
    public function setPassword($mValue)
    {
        $this->_password = (string) $mValue;
        return $this;
    }

    /**
     * Returns password
     *
     * @return string password
     */
    public function getPassword()
    {
        return $this->_password;
    }

    /**
     * Set salt, value is casted to string
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_Users
     */
    public function setSalt($mValue)
    {
        $this->_salt = (string) $mValue;
        return $this;
    }

    /**
     * Returns salt
     *
     * @return string salt
     */
    public function getSalt()
    {
        return $this->_salt;
    }

    /**
     * Set directory, value is casted to string
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_Users
     */
    public function setDirectory($mValue)
    {
        $this->_directory = (string) $mValue;
        return $this;
    }

    /**
     * Returns directory
     *
     * @return string directory
     */
    public function getDirectory()
    {
        return $this->_directory;
    }

    /**
     * Set allowed_ips, value is casted to string
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_Users
     */
    public function setAllowedIps($mValue)
    {
        $this->_allowed_ips = (string) $mValue;
        return $this;
    }

    /**
     * Returns allowed_ips
     *
     * @return string allowed_ips
     */
    public function getAllowedIps()
    {
        return $this->_allowed_ips;
    }

    /**
     * Set comment, value is casted to string
     *
     * @param mixed $mValue Value
     *
     * @return Marktjagd_Database_Entity_Users
     */
    public function setComment($mValue)
    {
        $this->_comment = (string) $mValue;
        return $this;
    }

    /**
     * Returns comment
     *
     * @return string comment
     */
    public function getComment()
    {
        return $this->_comment;
    }

    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_Users
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