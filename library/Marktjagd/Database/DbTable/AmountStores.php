<?php

class Marktjagd_Database_DbTable_AmountStores extends Marktjagd_Database_DbTable_Abstract {
    
    protected $_name = 'AmountStores';
    
    protected $_primary = 'idAmountStores';
    
    protected $_referenceMap = array (
        'IdCompany' => array(
         'columns'       => 'idCompany',
         'refTableClass' => 'Marktjagd_Database_DbTable_Company',
         'refColumns'    => 'idCompany')
    );
    
    /**
     * 
     * @param type $companyId
     * @return type
     */
    public function findByCompanyId($companyId) {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
                ->join('Company', 'Company.idCompany = AmountStores.idCompany')
                ->where('AmountStores.idCompany = ?', (int)$companyId)
                ->order('lastTimeChecked DESC');
        
        return $this->fetchRow($select);
    }
    
    /**
     * 
     * @param type $companyId
     * @param type $startDate
     * @param type $endDate
     * @return type
     */
    public function findByCompanyIdAndTime($companyId, $startDate, $endDate) {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
                ->join('Company', 'Company.idCompany = AmountStores.idCompany')
                ->where('AmountStores.idCompany = ?', (int)$companyId)
                ->where('UNIX_TIMESTAMP(lastTimeChecked) >= ?', (int)$startDate)
                ->where('UNIX_TIMESTAMP(lastTimeChecked) <= ?', (int)$endDate)
                ->order('lastTimeChecked DESC');
        
        return $this->fetchAll($select);
    }
    
    /**
     * 
     * @return type
     */
    public function findLatestState() {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name, array('AmountStores.idCompany', 'AmountStores.amountStores',
            'AmountStores.lastTimeModified', 'AmountStores.lastImport', 
            new Zend_Db_Expr('max(AmountStores.lastTimeChecked) as lastTimeChecked')))
                ->join('Company', 'Company.idCompany = AmountStores.idCompany')
                ->where('productCategory != ?', '')
                ->distinct(TRUE)
                ->group('AmountStores.idCompany');
        
        return $this->fetchAll($select);
    }
}