<?php

class Marktjagd_Database_DbTable_AdAnalysis extends Marktjagd_Database_DbTable_Abstract
{

    protected $_name = 'AdAnalysis';
    protected $_primary = 'idAdAnalysis';
    
    /**
     * 
     * @param int $idCompany
     * @param string $startDate
     * @param string $endDate
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findAdsForCompanyByIdAndTimeAndType($idCompany, $startDate, $endDate, $type)
    {
        $select = $this->select()->setIntegrityCheck(false)
                ->from($this->_name,
                    array(
                        'currentAd' => 'IF (SUM(currentAd) >= 1, 1, 0)',
                        'timeChecked' => 'DATE_FORMAT(timeChecked, \'%d\')',
                        'idCompany' => 'idCompany',
                        'targetAd' => 'targetAd',
                        'adType' => 'adType',
                        'idAdAnalysis' => 'idAdAnalysis'
                    ))
                ->where('idCompany = ?', (int) $idCompany)
                ->where('UNIX_TIMESTAMP(timeChecked) >= ?', $startDate)
                ->where('UNIX_TIMESTAMP(timeChecked) <= ?', $endDate)
                ->where('adType LIKE ?', '%' . $type . '%')
                ->group(array('timeChecked', 'idCompany', 'targetAd', 'adType','idAdAnalysis'))
                ->order('timeChecked');

        return $this->fetchAll($select);
    }
}
