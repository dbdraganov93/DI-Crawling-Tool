<?php

class Marktjagd_Database_DbTable_AmountProducts extends Marktjagd_Database_DbTable_Abstract {
    
    protected $_name = 'AmountProducts';
    
    protected $_primary = 'idAmountProducts';
    
    protected $_referenceMap = array (
        'IdCompany' => array(
         'columns'       => 'idCompany',
         'refTableClass' => 'Marktjagd_Database_DbTable_Company',
         'refColumns'    => 'idCompany')
    );
    
    /**
     * 
     * @param string $companyId
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findByCompanyId($companyId) {
        $select = $this->select();
        $select ->from($this->_name)
                ->where('idCompany = ?', (int)$companyId);
        
        return $this->fetchAll($select);
    }
    
    /**
     * 
     * @param string $companyId
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findLatestState($companyId) {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name, array('sum(amountProducts) as amountProducts',
            'lastTimeModified', 'lastTimeChecked'))
                ->join('Company', 'Company.idCompany = AmountProducts.idCompany')
                ->where('AmountProducts.idCompany = ?', (int)$companyId)
                ->group('lastTimeChecked')
                ->order('lastTimeChecked DESC');
        
        return $this->fetchRow($select);
    }
    
    public function findByCompanyIdAndTime($companyId, $startDate, $endDate) {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name,
            array(
                'amountProducts' => 'sum(amountProducts)',
                'startDate' => 'DATE(AmountProducts .startDate)',
                'endDate' => 'DATE(AmountProducts.endDate)',
                'idCompany' => 'AmountProducts.idCompany',
                'lastTimeModified' => 'AmountProducts.lastTimeModified',
                'lastTimeChecked' => 'AmountProducts.lastTimeChecked',
                'lastImport' => 'AmountProducts.lastImport'))
                ->where('idCompany = ?', (int)$companyId)
                ->where('UNIX_TIMESTAMP(lastTimeChecked) >= ?', (int)$startDate)
                ->where('UNIX_TIMESTAMP(lastTimeChecked) <= ?', (int)$endDate)
                ->group(array('lastTimeChecked', 'startDate', 'endDate', 'idCompany', 'lastTimeModified', 'lastImport'))
                ->order('lastTimeChecked DESC');
        
        return $this->fetchAll($select);
    }
}