<?php

class Marktjagd_Database_DbTable_AdvertisingSettings extends Marktjagd_Database_DbTable_Abstract
{

    protected $_name = 'AdvertisingSettings';
    protected $_primary = 'idAdvertisingSettings';

    public function findFutureAdsByCompanyId($companyId, $startDate)
    {
        $select = $this->select()->setIntegrityCheck(false)
                ->from($this->_name)
                ->where('idCompany = ?', (int) $companyId)
                ->where('UNIX_TIMESTAMP(nextDate) >= UNIX_TIMESTAMP(?)', $startDate)
                ->where('adStatus = 1');
        
        return $this->fetchAll($select);
    }
    
    public function deleteAdvertisingSetting($adId) {
        $update = $this->update(array('adStatus' => 'inactive'), 'idAdvertisingSettings = ' . (int) $adId);

        return $this->fetchAll($update);
    }
    
    public function findAdsByCompanyId($companyId) {
        $select = $this->select()->setIntegrityCheck(false)
                ->from($this->_name)
                ->where('idCompany = ?', (int) $companyId)
                ->where('adStatus = 1')
                ->order('nextDate');

        return $this->fetchAll($select);
    }
    
    /**
     * 
     * @param type $companyId
     * @return type
     */
    public function findActualAdsByCompanyId($companyId) {
        $select = $this->select()->setIntegrityCheck(false)
                ->from($this->_name)
                ->where('idCompany = ?', (int) $companyId)
                ->where('adStatus = 1')
                ->where('UNIX_TIMESTAMP(endDate) >= UNIX_TIMESTAMP(?)', date('Y-m-d') . ' 23:59:59')
                ->where('UNIX_TIMESTAMP(startDate) <= UNIX_TIMESTAMP(?)', date('Y-m-d') . ' 00:00:00')
                ->order('nextDate');

        return $this->fetchAll($select);
    }

    public function findSingleAd($adId) {
        $select = $this->select()->setIntegrityCheck(false)
                ->from($this->_name)
                ->where('idAdvertisingSettings = ?', (int) $adId)
                ->order('nextDate');

        return $this->fetchAll($select);
    }
}
