<?php

class Marktjagd_Database_DbTable_IProtoApiToken extends Marktjagd_Database_DbTable_Abstract {

    protected $_name = 'IProtoApiToken';
    protected $_primary = 'env';

    /**
     * @param string $env
     * @return Zend_Db_Table_Row_Abstract
     */
    public function findByEnv($env) {
        $select = $this->select();
        $select->from($this->_name)
            ->where('env = ?', $env);

        return $this->fetchRow($select);
    }

    public function insertToken($env, $token) {
        $this->insert(array(
            'env' => $env,
            'token' => $token
        ));
    }

    public function updateToken($env, $token) {
        $this->update(array(
            'token' => $token
        ), "env = '$env'"); // There does not seem to be a good way in Zend to run this query with a prepared statement…
    }
}
