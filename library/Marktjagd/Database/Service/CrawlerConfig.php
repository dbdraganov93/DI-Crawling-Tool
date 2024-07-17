<?php

class Marktjagd_Database_Service_CrawlerConfig extends Marktjagd_Database_Service_Abstract
{
    /**
     * Sucht nach Crawlern in der Datenbank anhand von CompanyId und Typ des Crawlers
     *
     * @param int $companyId
     * @param string $type
     * @param ?string $status
     * @param string $env
     * @return Marktjagd_Database_Collection_CrawlerConfig
     */
    public function findByCompanyTypeStatus($companyId, $type, $status, $env=Marktjagd_Database_Entity_CrawlerConfig::BACKEND_ENV_PROD)
    {
        $cCompanyType = new Marktjagd_Database_Collection_CrawlerConfig();

        $mCrawlerConfig = new Marktjagd_Database_Mapper_CrawlerConfig();
        $mCrawlerConfig->findByCompanyType($companyId, $type, $status, $cCompanyType, $env);

        return $cCompanyType;
    }

    /**
     * Findet alle Crawler für einen bestimmten Crawlertyp
     *
     * @param string $type articles|brochures|stores
     * @param string $sort DB-Feld, nach dem sortiert werden soll
     * @param string $env
     * @return Marktjagd_Database_Collection_CrawlerConfig
     */
    public function findByType($type, $sort=null, $env=Marktjagd_Database_Entity_CrawlerConfig::BACKEND_ENV_PROD)
    {
        $oCrawlerConfig = new Marktjagd_Database_Collection_CrawlerConfig();
        $mCrawlerConfig = new Marktjagd_Database_Mapper_CrawlerConfig();
        $mCrawlerConfig->findByType($oCrawlerConfig, $type, $sort, $env);

        return $oCrawlerConfig;
    }

    /**
     * Findet einen Crawler anhand der Konfigurations-Id
     * @param $idCrawlerConfig
     * @return Marktjagd_Database_Entity_CrawlerConfig
     */
    public function findById($idCrawlerConfig)
    {
        $eCrawlerConfig  = new Marktjagd_Database_Entity_CrawlerConfig();
        $mCrawlerConfig = new Marktjagd_Database_Mapper_CrawlerConfig();
        $mCrawlerConfig->findById($idCrawlerConfig, $eCrawlerConfig);
        return $eCrawlerConfig;
    }

    /**
     * Ermittelt zu allen Crawler-Autoren die Anzahl der aktiven Crawler
     *
     * @return array
     */
    public function countActiveCrawlerByUser()
    {
        $mCrawlerConfig = new Marktjagd_Database_Mapper_CrawlerConfig();
        $aResult = $mCrawlerConfig->countActiveCrawlerByUser();

        return $aResult;
    }

    /**
     * Ermittelt die Anzahl alter und neuer Crawler
     *
     * @return array
     */
    public function countCrawlerByVersionType()
    {
        $mCrawlerConfig = new Marktjagd_Database_Mapper_CrawlerConfig();
        $aResult = $mCrawlerConfig->countCrawlerByVersionType();

        return $aResult;
    }

    /**
     * Ermittelt die Anzahl von Standort-, Artikel-, PDF-Crawler
     *
     * @return array
     */
    public function countCrawlerByType()
    {
        $mCrawlerConfig = new Marktjagd_Database_Mapper_CrawlerConfig();
        $aResult = $mCrawlerConfig->countCrawlerByType();

        return $aResult;
    }

    /**
     * Ermittelt die Anzahl modifizierter Standort-, Artikel-, PDF-Crawler
     *
     * @return array
     */
    public function countModifiedByType()
    {
        $mCrawlerConfig = new Marktjagd_Database_Mapper_CrawlerConfig();
        $aResult = $mCrawlerConfig->countModifiedByType();

        return $aResult;
    }
}