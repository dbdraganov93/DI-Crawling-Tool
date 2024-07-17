<?php

/**
 * Klasse zum Generieren von Passwörtern
 *
 * Class Marktjagd_Entity_Password
 */
class Marktjagd_Entity_Password
{
    /**
     * Passwortstring (ungehashed)
     *
     * @var string
     */
    protected $_password;

    /**
     * Salt, welches dem Passwort vor dem Hashen vorangestellt wird
     *
     * @var string
     */
    protected $_salt;

    /**
     * Gehashtes Passwort, aus Hash von Salt + Passwort
     *
     * @var string
     */
    protected $_passwordHashed;

    /**
     * Art des Hashs
     *
     * @var string
     */
    protected $_hashType;

    /**
     * @return string
     */
    public function getHashType() {
        return $this->_hashType;
    }

    /**
     * @param string $hashType
     *
     * @return Marktjagd_Entity_Password
     */
    public function setHashType($hashType) {
        $this->_hashType = $hashType;
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
     * @return Marktjagd_Entity_Password
     */
    public function setPassword($password) {
        $this->_password = $password;
        return $this;
    }

    /**
     * @return string
     */
    public function getPasswordHashed() {
        return $this->_passwordHashed;
    }

    /**
     * @param string $passwordHashed
     *
     * @return Marktjagd_Entity_Password
     */
    public function setPasswordHashed($passwordHashed) {
        $this->_passwordHashed = $passwordHashed;
        return $this;
    }

    /**
     * @return string
     */
    public function getSalt() {
        return $this->_salt;
    }

    /**
     * @param string $salt
     *
     * @return Marktjagd_Entity_Password
     */
    public function setSalt($salt) {
        $this->_salt = $salt;
        return $this;
    }


}
