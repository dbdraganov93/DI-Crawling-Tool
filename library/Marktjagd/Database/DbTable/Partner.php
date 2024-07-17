<?php

class Marktjagd_Database_DbTable_Partner extends Marktjagd_Database_DbTable_Abstract {

    protected $_name = 'Partner';
    protected $_primary = 'idPartner';

    protected $_referenceMap = array(
        'IdPartner' => array(
            'columns'       => 'idPartner',
            'refTableClass' => 'Marktjagd_Database_DbTable_Company',
            'refColumns'    => 'idPartner'));

    /**
     * @param $companyId
     * @return Zend_Db_Table_Row_Abstract
     */
    public function findByCompanyId($companyId) {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
               ->join('Company', 'Partner.idPartner = Company.idPartner')
                ->where('Company.idCompany = ?', (int) $companyId);

        return $this->fetchRow($select);
    }
}
