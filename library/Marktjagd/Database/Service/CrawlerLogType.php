<?php
class Marktjagd_Database_Service_CrawlerLogType
{
    /**
     * Ermittelt alle CrawlerLogTypes
     * @return Marktjagd_Database_Collection_CrawlerLogType
     */
    public function findAll()
    {
        $cCrawlerLogType = new Marktjagd_Database_Collection_CrawlerLogType();
        $mCrawlerLogType = new Marktjagd_Database_Mapper_CrawlerLogType();
        $mCrawlerLogType->fetchAll(null, $cCrawlerLogType);

        return $cCrawlerLogType;
    }
}