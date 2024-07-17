<?php

class Marktjagd_Database_Mapper_CrawlerLog extends Marktjagd_Database_Mapper_Abstract
{
    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_CrawlerLog
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }

    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_CrawlerLog $oCrawlerLog Object data
     * @param bool $bNull Save also null values
     *
     * @return int|mixed
     */
    public function save(Marktjagd_Database_Entity_CrawlerLog $oCrawlerLog, $bNull = false)
    {
        return parent::_save($oCrawlerLog, $bNull);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_CrawlerLog  $oCrawlerLog Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_CrawlerLog $oCrawlerLog)
    {
        return parent::_find($mId, $oCrawlerLog);
    }

    /**
     * Count running processes (by type)
     *
     * @param $type
     * @return int
     */
    public function countRunningProcesses($type = null)
    {
        $count = 0;
        $result = $this->getDbTable()->countRunningProcesses($type);
        if ($result) {
            $count = (int) $result['count'];
        }

        return $count;
    }

    /**
     * Ermittelt in Abhängigkeit der Konfiguration die nächsten Crawler, die gestartet werden sollen
     *
     * @param $processSlots
     * @param $articleSlots
     * @param $brochureSlots
     * @param $storeSlots
     * @param Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog
     * @return bool
     */
    public function findNextProcesses(
        $processSlots,
        $articleSlots,
        $brochureSlots,
        $storeSlots,
        Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog)
    {
        $result = $this->getDbTable()->findNextProcesses($processSlots, $articleSlots, $brochureSlots, $storeSlots);
        if (count($result)) {
            $oCrawlerLog->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Prüft, ob von einem Crawler bereits eine Instanz gestartet wurde
     *
     * @param $idCrawlerConfig
     * @return bool
     */
    public function isRunning($idCrawlerConfig)
    {
        $count = 0;
        $result = $this->getDbTable()->isRunning($idCrawlerConfig);
        if ($result) {
            $count = (int) $result['count'];
        }

        if ($count > 0) {
            return true;
        }

        return false;
    }

    /**
     * Ermittelt die letzten Logeinträge / Prozesse für einen Crawler
     *
     * @param $idCrawlerConfig
     * @param $limit
     * @param Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog
     * @return bool
     */
    public function findLastProcessesByCrawler($idCrawlerConfig, $limit, Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog)
    {
        $result = $this->getDbTable()->findLastProcessesByCrawler($idCrawlerConfig, $limit);
        if (count($result)) {
            $oCrawlerLog->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Ermittelt alle aktuell laufenden Crawler
     *
     * @param Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog
     * @return bool
     */
    public function findRunningProcesses(Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog)
    {
        $result = $this->getDbTable()->findRunningProcesses();
        if (count($result)) {
            $oCrawlerLog->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Ermittelt alle zukünfigen Crawler (Status: waiting) eines Typs
     *
     * @param $type
     * @param Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog
     * @return bool
     */
    public function findScheduledProcessesByType($type, Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog)
    {
        $result = $this->getDbTable()->findScheduledProcessesByType($type);
        if (count($result)) {
            $oCrawlerLog->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Ermittelt einen CrawlerLog-Eintrag anhand seiner Id, incl. der Infos aus relevanten Join-Tabellen
     *
     * @param $idCrawlerLog
     * @param Marktjagd_Database_Entity_CrawlerLog $oCrawlerLog
     * @return bool
     */
    public function findById($idCrawlerLog, Marktjagd_Database_Entity_CrawlerLog $oCrawlerLog)
    {
        $result = $this->getDbTable()->findById($idCrawlerLog);
        if ($result) {
            $oCrawlerLog->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Ermittelt alle abgeschlossenen Crawler und filtert diese anhand von $aOptions
     *
     * @param array $aOptions
     * @param Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog
     * @return bool
     */
    public function findFinished($aOptions, Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog)
    {
        $result = $this->getDbTable()->findFinished($aOptions);
        if (count($result)) {
            $oCrawlerLog->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Ermittelt alle Prozesse, welche gerade in die API importieren
     *
     * @param Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog
     * @return bool
     */
    public function findImporting(Marktjagd_Database_Collection_CrawlerLog $oCrawlerLog)
    {
        $result = $this->getDbTable()->findImporting();
        if (count($result)) {
            $oCrawlerLog->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Berechnet aus den letzten (5) erfolgreichen Crawlerläufen die durchschnittliche Laufzeit des Crawlers
     *
     * @param $idCrawlerConfig
     * @return int|bool
     */
    public function calculateEstimatedRuntime($idCrawlerConfig)
    {
        $result = $this->getDbTable()->calculateEstimatedRuntime($idCrawlerConfig);
        if (count($result)) {
            return $result[0]['sumTimeDiff'];
        }

        return false;
    }

    /**
     * Ermittelt instabile Crawler und die Häufigkeit, wie oft sie kaputt sind
     *
     * @param int $days
     *
     * @return array
     */
    public function findInstable($days)
    {
        $result = $this->getDbTable()->findInstable($days);
        $aCrawler = array();

        foreach ($result as $row) {
            $aCrawler[$row['idCrawlerConfig']] = array(
                'failureRate' => $row['failureRate'],
                'success' => $row['success'],
                'failed' => $row['failed'],
                'type' => $row['type'],
                'name' => $row['name']
            );
        }

        return $aCrawler;
    }
}