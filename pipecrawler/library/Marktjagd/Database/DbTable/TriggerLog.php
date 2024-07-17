<?php

/**
 * Class Marktjagd_Database_DbTable_TriggerLog
 */
class Marktjagd_Database_DbTable_TriggerLog extends Marktjagd_Database_DbTable_Abstract
{
    protected $_name = 'TriggerLog';

    protected $_primary = 'idTriggerLog';

    protected $_referenceMap = array(
        'IdTriggerType' => array(
            'columns'       => 'idTriggerType',
            'refTableClass' => 'Marktjagd_Database_DbTable_TriggerType',
            'refColumns'    => 'idTriggerType'),
        'IdCompany' => array(
            'columns'       => 'idCompany',
            'refTableClass' => 'Marktjagd_Database_DbTable_Company',
            'refColumns'    => 'idCompany'));

    /**
     * Ermittelt alle FTP-Aktionen der letzten $hours Stunden
     *
     * @param int $hours Stunden in die Vergangenheit, bis zu denen die FTP-Aktionen aufgelistet werden sollen
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findForLastHours($hours)
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
               ->join('Company', 'TriggerLog.idCompany = Company.idCompany')
               ->where('TIMESTAMPDIFF(HOUR, TriggerLog.time, NOW()) <= ?', (int) $hours);

        return $this->fetchAll($select);
    }
}