<?php

class Marktjagd_Database_DbTable_CompaniesWithAdMaterial extends Marktjagd_Database_DbTable_Abstract
{

    protected $_name = 'CompaniesWithAdMaterial';
    protected $_primary = 'idCompaniesWithAdMaterial';

    public function findCompletenessByTimeSpan($startDate, $endDate, $idCompany)
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
                ->join('Company', 'Company.idCompany = CompaniesWithAdMaterial.idCompany')
                ->where('UNIX_TIMESTAMP(lastTimeChecked) >= ?', $startDate)
                ->where('UNIX_TIMESTAMP(lastTimeChecked) <= ?', $endDate);

        if (!is_null($idCompany)) {
            $select->where('CompaniesWithAdMaterial.idCompany = ?', (int) $idCompany);
        }
        $select->group(array('Company.idCompany', 'DATE_FORMAT(lastTimeChecked, \'%d\')'))
                ->order('Company.idCompany');
        
        return $this->fetchAll($select);
    }

}
