<?php

/**
 * Class Marktjagd_Database_DbTable_QualityCheckErrors
 */
class Marktjagd_Database_DbTable_QualityCheckErrors extends Marktjagd_Database_DbTable_Abstract
{

    protected $_name = 'QualityCheckErrors';
    protected $_primary = 'idQualityCheckErrors';
    protected $_referenceMap = array(
        'IdCompany' => array(
            'columns' => 'idCompany',
            'refTableClass' => 'Marktjagd_Database_DbTable_Company',
            'refColumns' => 'idCompany')
    );

    /**
     * Findet alle aktuellen Fehler eines Unternehmens
     * 
     * @param string $idCompany
     * @return Marktjagd_Database_Collection_QualityCheckErrors
     */
    public function findByCompanyId($idCompany)
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
                ->join('Company', 'Company.idCompany = QualityCheckErrors.idCompany')
                ->where('QualityCheckErrors.idCompany = ?', (int) $idCompany)
                ->where('QualityCheckErrors.errorStatus = 1')
                ->order('lastTimeModified ASC');

        return $this->fetchAll($select);
    }

    /**
     * Findet alle aktuellen Fehler eines Typs eines Unternehmens
     * 
     * @param string $idCompany
     * @param string $type
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function findByCompanyIdAndType($idCompany, $type)
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
                ->join('Company', 'Company.idCompany = QualityCheckErrors.idCompany')
                ->where('QualityCheckErrors.idCompany = ?', (int) $idCompany)
                ->where('QualityCheckErrors.type LIKE ?', $type)
                ->where('QualityCheckErrors.errorStatus = 1')
                ->order('idQualityCheckErrors DESC');

        return $this->fetchRow($select);
    }

    /**
     * Findet die neu hinzugefügten Fehler in einem bestimmten Zeitfenster
     * 
     * @param string $startTime
     * @param string $endTime
     * @return Marktjagd_Database_Collection_QualityCheckErrors
     */
    public function findLatestQualityCheckErrorsAdditions($startTime, $endTime)
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
                ->join('Company', 'Company.idCompany = QualityCheckErrors.idCompany')
                ->joinLeft('User', 'User.idUser = QualityCheckErrors.idUser')
                ->where('QualityCheckErrors.errorStatus = 1')
                ->where('QualityCheckErrors.timeAdded IS NOT NULL')
                ->where('UNIX_TIMESTAMP(QualityCheckErrors.timeAdded) >= UNIX_TIMESTAMP(?)', $startTime)
                ->where('UNIX_TIMESTAMP(QualityCheckErrors.timeAdded) <= UNIX_TIMESTAMP(?)', $endTime)
                ->group('QualityCheckErrors.actualAmount')
                ->group('QualityCheckErrors.lastAmount')
                ->group('QualityCheckErrors.type')
                ->order('QualityCheckErrors.idCompany');

        return $this->fetchAll($select);
    }

    /**
     * Ändert den Status eines Fehlers und fügt den Nutzer hinzu
     * 
     * @param string $idQualityCheckErrors
     * @param string $status
     * @param string $user
     * @return bool
     */
    public function changeStatus($idQualityCheckErrors, $status, $user)
    {
        $data = array(
            'errorStatus' => $status,
            'idUser' => $user
        );

        $update = $this->update($data, 'idQualityCheckErrors = ' . $idQualityCheckErrors);

        return $update;
    }

}
