<?php

class Marktjagd_Database_DbTable_CompanyAdditionalInfos extends Marktjagd_Database_DbTable_Abstract
{
    protected $_name = 'CompanyAdditionalInfos';

    protected $_primary = 'idAdditionalInfos';

    /**
     * 
     * @param string $companyId
     * @return Zend_Db_Table_Row_Abstract
     */
    public function findByCompanyId($companyId) {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
                ->where('idCompany = ?', (int)$companyId);
        
        return $this->fetchRow($select);
    }
}