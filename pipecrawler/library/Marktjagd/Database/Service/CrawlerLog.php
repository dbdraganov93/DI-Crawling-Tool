<?php
class Marktjagd_Database_Service_CrawlerLog
{
    /**
     * count running processes (by type)
     *
     * @param string $type articles|brochures|stores|null
     * @return int
     */
    public function countRunningProcesses($type = null)
    {
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();
        return $mCrawlerLog->countRunningProcesses($type);
    }

    /**
     * Ermittelt in Abhängigkeit der Konfiguration die nächsten Crawler, die gestartet werden sollen
     *
     * @param int $processSlots max. verfügbare Crawlerslots
     * @param int $articleSlots max. verfügbare Artikel-Crawlerslots
     * @param int $brochureSlots max. verfügbare PDF-Crawlerslots
     * @param int $storeSlots max. verfügbare Store-Crawlerslots
     *
     * @return Marktjagd_Database_Collection_CrawlerLog
     */
    public function findNextProcesses($processSlots, $articleSlots, $brochureSlots, $storeSlots)
    {
        $cCrawlerLog = new Marktjagd_Database_Collection_CrawlerLog();
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();

        $mCrawlerLog->findNextProcesses($processSlots, $articleSlots, $brochureSlots, $storeSlots, $cCrawlerLog);

        return $cCrawlerLog;
    }

    /**
     * Prüft, ob von einem Crawler bereits eine Instanz gestartet wurde
     *
     * @param $idCrawlerConfig
     * @return bool
     */
    public function isRunning($idCrawlerConfig)
    {
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();
        return $mCrawlerLog->isRunning($idCrawlerConfig);
    }

    /**
     * Ermittelt die letzten Logeinträge / Prozesse für einen Crawler
     *
     * @param $idCrawlerConfig
     * @param $limit
     * @return Marktjagd_Database_Collection_CrawlerLog
     */
    public function findLastProcessesByCrawler($idCrawlerConfig, $limit)
    {
        $cCrawlerLog = new Marktjagd_Database_Collection_CrawlerLog();
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();
        $mCrawlerLog->findLastProcessesByCrawler($idCrawlerConfig, $limit, $cCrawlerLog);

        return $cCrawlerLog;
    }

    /**
     * Ermittelt alle aktuell laufenden Crawler
     *
     * @return Marktjagd_Database_Collection_CrawlerLog
     */
    public function findRunningProcesses()
    {
        $cCrawlerLog = new Marktjagd_Database_Collection_CrawlerLog();
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();
        $mCrawlerLog->findRunningProcesses($cCrawlerLog);

        return $cCrawlerLog;
    }

    /**
     * Ermittelt alle zukünfigen Crawler (Status: waiting) eines Typs
     *
     * @param $type 1 => articles, 2 => brochures, 3 => stores
     * @return Marktjagd_Database_Collection_CrawlerLog
     */
    public function findScheduledProcessesByType($type)
    {
        $cCrawlerLog = new Marktjagd_Database_Collection_CrawlerLog();
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();
        $mCrawlerLog->findScheduledProcessesByType($type, $cCrawlerLog);

        return $cCrawlerLog;
    }

    /**
     * Ermittelt einen CrawlerLog-Eintrag anhand seiner Id, incl. der Infos aus relevanten Join-Tabellen
     *
     * @param int $idCrawlerLog
     * @return Marktjagd_Database_Entity_CrawlerLog
     */
    public function findById($idCrawlerLog)
    {
        $eCrawlerLog = new Marktjagd_Database_Entity_CrawlerLog();
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();
        $mCrawlerLog->findById($idCrawlerLog, $eCrawlerLog);

        return $eCrawlerLog;
    }

    /**
     * Ermittelt alle abgeschlossenen Crawler und filtert diese anhand von $aOptions
     *
     * @param array $aOptions
     * @return Marktjagd_Database_Collection_CrawlerLog
     */
    public function findFinished($aOptions = array())
    {
        $cCrawlerLog = new Marktjagd_Database_Collection_CrawlerLog();
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();
        $mCrawlerLog->findFinished($aOptions, $cCrawlerLog);

        return $cCrawlerLog;
    }

    /**
     * Ermittelt alle Prozesse, welche gerade in die API importieren
     *
     * @return Marktjagd_Database_Collection_CrawlerLog
     */
    public function findImporting()
    {
        $cCrawlerLog = new Marktjagd_Database_Collection_CrawlerLog();
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();
        $mCrawlerLog->findImporting($cCrawlerLog);

        return $cCrawlerLog;
    }

    /**
     * Berechnet aus den letzten (5) erfolgreichen Crawlerläufen die durchschnittliche Laufzeit des Crawlers
     *
     * @param $idCrawlerConfig
     * @return int|bool
     */
    public function calculateEstimatedRuntime($idCrawlerConfig)
    {
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();
        return $mCrawlerLog->calculateEstimatedRuntime($idCrawlerConfig);
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
        $mCrawlerLog = new Marktjagd_Database_Mapper_CrawlerLog();
        return $mCrawlerLog->findInstable($days);
    }

}