<?php

class Marktjagd_Database_DbTable_CrawlerLog extends Marktjagd_Database_DbTable_Abstract
{
    protected $_name = 'CrawlerLog';

    protected $_primary = 'idCrawlerLog';

    protected $_referenceMap = array(
      'IdCrawlerConfig' => array(
         'columns'       => 'idCrawlerConfig',
         'refTableClass' => 'Marktjagd_Database_DbTable_CrawlerConfig',
         'refColumns'    => 'idCrawlerConfig'),
      'IdCrawlerLogType' => array(
         'columns'       => 'idCrawlerLogType',
         'refTableClass' => 'Marktjagd_Database_DbTable_CrawlerLogType',
         'refColumns'    => 'idCrawlerLogType'));

    /**
     * Zählt die aktuell laufenden Prozesse, wenn $type übergeben wurde => in Abhängigkeit vom Typ
     *
     * @param null $type
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function countRunningProcesses($type=null)
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name, array('count' => 'COUNT(CrawlerLog.idCrawlerLog)'));

        if ($type) {
            switch ($type) {
                case 'articles':
                    $type = 1;
                    break;
                case 'brochures':
                    $type = 2;
                    break;
                case 'stores':
                    $type = 3;
                    break;
                default:
                    $type = null;
            }

            $select->join('CrawlerConfig', 'CrawlerConfig.idCrawlerConfig = CrawlerLog.idCrawlerConfig '
                . 'AND CrawlerConfig.idCrawlerType = ' . (string) $type, array());
        }

        $select->where('CrawlerLog.idCrawlerLogType = ?', Crawler_Generic_Response::PROCESSING);

        $result = $this->fetchRow($select);
        return $result;
    }

    /**
     * Ermittelt in Abhängigkeit der Konfiguration die nächsten Crawler, die gestartet werden sollen
     *
     * @param int $processSlots
     * @param int $articleSlots
     * @param int $brochureSlots
     * @param int $storeSlots
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findNextProcesses($processSlots, $articleSlots, $brochureSlots, $storeSlots)
    {
        if ($processSlots > 0) {
            $aUnion = array();
            if ($articleSlots > 0) {
                $aUnion[] = '(' . $this->_generateSelectByType(1, $articleSlots) . ')';
            }

            if ($brochureSlots > 0) {
                $aUnion[] = '(' . $this->_generateSelectByType(2, $brochureSlots) . ')';
            }

            if ($storeSlots > 0) {
                $aUnion[] = '(' . $this->_generateSelectByType(3, $storeSlots) . ')';
            }

            $select = $this->select()->setIntegrityCheck(false)
                           ->union($aUnion, Zend_Db_Select::SQL_UNION_ALL)
                           // nach siebter (Prio), fünfzehnter (Crawler-Typ)
                           // und vierter (Scheduled) Spalte sortieren
                           ->order(array('7 ASC', '15 ASC', '4 ASC'))
                           ->limit($processSlots);
            $retVal = $this->fetchAll($select);
        } else {
            $retVal = new Zend_Db_Table_Rowset(array());
        }
        return $retVal;
    }

    /**
     * Generiert den Subselect für die Funktion findNextProcesses anhand des übergebenen Typ
     *
     * @param $type 1 => articles | 2 => brochures | 3 => stores
     * @param $limit
     * @return Zend_Db_Select
     */
    protected function _generateSelectByType($type, $limit)
    {
        $select = $this->select()->setIntegrityCheck(false)
                       ->from($this->_name)
                       ->join('CrawlerConfig', 'CrawlerConfig.idCrawlerConfig = CrawlerLog.idCrawlerConfig'
                            . ' AND CrawlerConfig.idCrawlerType = ' . (string) $type
                            . ' AND CrawlerConfig.systemRunning = "crawler"'
                            . ' AND CrawlerLog.idCrawlerLogType = ' . Crawler_Generic_Response::WAITING)
                       ->order('CrawlerLog.prio')
                       ->order('CrawlerLog.scheduled ASC')
                       ->limit($limit);
        return $select;
    }

    /**
     * Prüft, ob von einem Crawler bereits eine Instanz gestartet wurde
     *
     * @param $idCrawlerConfig
     * @return Zend_Db_Table_Row_Abstract
     */
    public function isRunning($idCrawlerConfig)
    {
        $select = $this->select()->setIntegrityCheck(false)
                       ->from($this->_name, array('count' => 'COUNT(CrawlerLog.idCrawlerLog)'))
                       ->where('CrawlerLog.idCrawlerConfig = ?', (string) $idCrawlerConfig)
                       ->where('(CrawlerLog.idCrawlerLogType = ' . Crawler_Generic_Response::WAITING
                            . ' OR CrawlerLog.idCrawlerLogType = ' . Crawler_Generic_Response::PROCESSING . ')');
        return $this->fetchRow($select);
    }

    /**
     * Ermittelt die letzten Logeinträge / Prozesse für einen Crawler
     *
     * @param $idCrawlerConfig
     * @param $limit
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findLastProcessesByCrawler($idCrawlerConfig, $limit)
    {
        $select = $this->select()->setIntegrityCheck(false)
                       ->from($this->_name)
                       ->join('CrawlerLogType', 'CrawlerLog.idCrawlerLogType = CrawlerLogType.idCrawlerLogType')
                       ->where('CrawlerLog.idCrawlerConfig = ?', (string) $idCrawlerConfig)
                       ->order('CrawlerLog.scheduled DESC')
                       ->limit((int) $limit);

        return $this->fetchAll($select);
    }

    /**
     * Ermittelt alle aktuell laufenden Crawler
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findRunningProcesses()
    {
        $select = $this->select()->setIntegrityCheck(false)
                       ->from($this->_name)
                       ->join('CrawlerConfig', 'CrawlerLog.idCrawlerConfig = CrawlerConfig.idCrawlerConfig')
                       ->join('Company', 'CrawlerConfig.idCompany = Company.idCompany')
                       ->join('CrawlerType', 'CrawlerConfig.idCrawlerType = CrawlerType.idCrawlerType')
                       ->where('CrawlerLog.idCrawlerLogType = 1')
                       ->order('CrawlerType.type ASC')
                       ->order('CrawlerLog.start ASC');

        return $this->fetchAll($select);
    }

    /**
     * Ermittelt alle zukünfigen Crawler (Status: waiting) eines Typs
     *
     * @param $type
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findScheduledProcessesByType($type)
    {
        $select = $this->select()->setIntegrityCheck(false)
            ->from($this->_name)
            ->join('CrawlerConfig', 'CrawlerLog.idCrawlerConfig = CrawlerConfig.idCrawlerConfig')
            ->join('Company', 'CrawlerConfig.idCompany = Company.idCompany')
            ->join('CrawlerType', 'CrawlerConfig.idCrawlerType = CrawlerType.idCrawlerType')
            ->where('CrawlerLog.idCrawlerLogType = 6')
            ->where('CrawlerConfig.idCrawlerType = ?', (int) $type)
            ->order('CrawlerLog.prio ASC')
            ->order('CrawlerLog.scheduled ASC');

        return $this->fetchAll($select);
    }

    /**
     * Ermittelt einen CrawlerLog-Eintrag anhand seiner Id, incl. der Infos aus relevanten Join-Tabellen
     *
     * @param $idCrawlerLog
     * @return null|Zend_Db_Table_Row_Abstract
     */
    public function findById($idCrawlerLog)
    {
        $select = $this->select()->setIntegrityCheck(false)
                       ->from($this->_name)
                       ->join('CrawlerConfig', 'CrawlerLog.idCrawlerConfig = CrawlerConfig.idCrawlerConfig')
                       ->join('Company', 'CrawlerConfig.idCompany = Company.idCompany')
                       ->join('CrawlerLogType', 'CrawlerLog.idCrawlerLogType = CrawlerLogType.idCrawlerLogType')
                       ->join('CrawlerType', 'CrawlerType.idCrawlerType = CrawlerConfig.idCrawlerType')
                       ->join('Author', 'Author.idAuthor = CrawlerConfig.idAuthor')
                       ->where('CrawlerLog.idCrawlerLog = ?', (string) $idCrawlerLog);
        return $this->fetchRow($select);
    }

    /**
     * Ermittelt alle abgeschlossenen Crawler und filtert diese anhand von $aOptions
     *
     * @param $aOptions
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findFinished($aOptions)
    {
        $select = $this->select()->setIntegrityCheck(false)
                       ->from($this->_name)
                       ->join('CrawlerConfig', 'CrawlerLog.idCrawlerConfig = CrawlerConfig.idCrawlerConfig')
                       ->join('Company', 'CrawlerConfig.idCompany = Company.idCompany')
                       ->join('CrawlerType', 'CrawlerConfig.idCrawlerType = CrawlerType.idCrawlerType')
                       ->where('CrawlerLog.idCrawlerLogType != 1')
                       ->where('CrawlerLog.idCrawlerLogType != 6');

        if (array_key_exists('type', $aOptions)) {
            $select->where('CrawlerConfig.idCrawlerType = ?', $aOptions['type']);
        }

        if (array_key_exists('period', $aOptions)) {
            $select->where('DATEDIFF(CURDATE(), CrawlerLog.end) <= ?', $aOptions['period']);
        }

        if (array_key_exists('companyId', $aOptions)) {
            $select->where('CrawlerConfig.idCompany = ?', $aOptions['companyId']);
        }

        if (array_key_exists('status', $aOptions)) {
            if (!is_array($aOptions['status'])) {
                $select->where('CrawlerLog.idCrawlerLogType = ?', (int) $aOptions['status']);
            } else {
                $select->where('CrawlerLog.idCrawlerLogType IN (?)', $aOptions['status']);
            }
        }

        $select->order('CrawlerLog.end DESC');

        if (array_key_exists('limit', $aOptions)) {
            $select->limit($aOptions['limit']);
        }

        if (!count($aOptions)) {
            $select->limit(100);
        }

        return $this->fetchAll($select);
    }

    /**
     * Ermittelt alle Prozesse, welche gerade in die API importieren
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findImporting()
    {
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name)
               ->join('CrawlerConfig', 'CrawlerLog.idCrawlerConfig = CrawlerConfig.idCrawlerConfig')
               ->join('Company', 'CrawlerConfig.idCompany = Company.idCompany')
               ->join('CrawlerType', 'CrawlerConfig.idCrawlerType = CrawlerType.idCrawlerType')
               ->where('CrawlerLog.idCrawlerLogType = ?', Crawler_Generic_Response::IMPORT_PROCESSING);

        return $this->fetchAll($select);
    }

    /**
     * Berechnet aus den letzten (5) erfolgreichen Crawlerläufen die durchschnittliche Laufzeit des Crawlers
     *
     * @param $idCrawlerConfig
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function calculateEstimatedRuntime($idCrawlerConfig)
    {
        $subselect = $this->select()->setIntegrityCheck(false);
        $subselect->from(
            array($this->_name),
            array(
                'timeDiff' => new Zend_Db_Expr('TIMESTAMPDIFF(SECOND, CrawlerLog.start, CrawlerLog.end)'),
                'idCrawlerConfig' => 'CrawlerLog.idCrawlerConfig'
            )
        )
                  ->where('CrawlerLog.idCrawlerConfig = ?', (int) $idCrawlerConfig)
                  ->where('CrawlerLog.idCrawlerLogType IN (2,4,7,8,9,10,11)')
                  ->order('CrawlerLog.idCrawlerLog DESC')
                  ->limit(5);



        $select = $this->select()->setIntegrityCheck(false);
        $select->from(
            array(
                't' => $subselect
            ),
            array(
                'sumTimeDiff' => new Zend_Db_Expr('CEIL(SUM(t.timediff)/(60 * count(t.idCrawlerConfig)))')
            )
        )
               ->group('t.idCrawlerConfig');

        return $this->fetchAll($select);
    }

    /**
     * Ermittelt instabile Crawler und die Häufigkeit, wie oft sie kaputt sind
     *
     * @param int $days
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findInstable($days)
    {
        $subselectSuccess = $select = $this->select()->setIntegrityCheck(false);
        $subselectSuccess->from(
            $this->_name,
            array(
                'countSuccess' => new Zend_Db_Expr('COUNT(CrawlerLog.idCrawlerLog)'),
                'idCrawlerConfig' => 'CrawlerLog.idCrawlerConfig'
                ))
                        ->where('CrawlerLog.idCrawlerLogType IN(2,4,7,8,9,10,11)')
                        ->where('DATEDIFF(CURDATE(), CrawlerLog.end) < ?', (int) $days)
                        ->group('CrawlerLog.idCrawlerConfig');


        $subselectFailed = $select = $this->select()->setIntegrityCheck(false);
        $subselectFailed->from(
            $this->_name,
            array(
                'countFailed' => new Zend_Db_Expr('COUNT(CrawlerLog.idCrawlerLog)'),
                'idCrawlerConfig' => 'CrawlerLog.idCrawlerConfig'
            ))
                         ->where('CrawlerLog.idCrawlerLogType IN(3)')
                         ->where('DATEDIFF(CURDATE(), CrawlerLog.end) < ?', (int) $days)
                         ->group('CrawlerLog.idCrawlerConfig');

        $select = $this->select()->setIntegrityCheck(false);
        $select->from(
                    $this->_name,
                    array(
                        'success' => new Zend_Db_Expr('IF(Success.countSuccess, Success.countSuccess, 0)'),
                        'failed' => new Zend_Db_Expr('IF(Failed.countFailed, Failed.countFailed, 0)'),
                        'failureRate' => new Zend_Db_Expr(
                            '(IF(Failed.countFailed, Failed.countFailed, 0)/'
                          . '(IF(Failed.countFailed, Failed.countFailed, 0)+IF(Success.countSuccess, Success.countSuccess, 0)))'
                        )
                    )
                )
               ->join('CrawlerConfig', 'CrawlerLog.idCrawlerConfig = CrawlerConfig.idCrawlerConfig', array('idCrawlerConfig' => 'CrawlerConfig.idCrawlerConfig'))
               ->join('Company', 'CrawlerConfig.idCompany = Company.idCompany', array('name' => 'Company.name'))
               ->join('CrawlerType', 'CrawlerConfig.idCrawlerType = CrawlerType.idCrawlerType', array('type' => 'CrawlerType.type'))
               ->joinLeft(
                   array('Success' => $subselectSuccess),
                   'Success.idCrawlerConfig = CrawlerLog.idCrawlerConfig',
                   array(
                   )
               )
               ->joinLeft(
                   array('Failed' => $subselectFailed),
                   'Failed.idCrawlerConfig = CrawlerLog.idCrawlerConfig',
                   array()
               )
               ->orWhere('Success.countSuccess > 0')
               ->orWhere('Failed.countFailed > 0')
               ->group(array('CrawlerLog.idCrawlerConfig', 'success', 'failed', 'failureRate', 'Company.name', 'CrawlerType.type', 'CrawlerConfig.idCrawlerConfig'))
               ->order('failureRate DESC')
               ->order('failed DESC')
               ->order('success ASC');

        return $this->fetchAll($select);
    }
}