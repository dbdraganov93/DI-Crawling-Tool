<?php

class Marktjagd_Database_DbTable_Company extends Marktjagd_Database_DbTable_Abstract {

    protected $_name = 'Company';
    protected $_primary = 'idCompany';
    protected $_referenceMap = array(
        'IdPartner' => array(
            'columns'       => 'idPartner',
            'refTableClass' => 'Marktjagd_Database_DbTable_Partner',
            'refColumns'    => 'idPartner'),
        );

    public function findByProductType($aProduct) {
        $select = $this->select();
        $select->from($this->_name)
                ->where('Company.productCategory IN (?)', $aProduct);

        return $this->fetchAll($select);
    }

}
