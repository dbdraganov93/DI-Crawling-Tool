<?php

class Marktjagd_Database_Service_Status extends Marktjagd_Database_Service_Abstract
{
    /**
     * Ermittelt alle verfügbaren Crawler-Status
     * @return Marktjagd_Database_Collection_Status
     */
    public function findAll()
    {
        $cCrawlerStatus = new Marktjagd_Database_Collection_Status();
        $mCrawlerStatus = new Marktjagd_Database_Mapper_Status();
        $mCrawlerStatus->fetchAll(null, $cCrawlerStatus);
        return $cCrawlerStatus;
    }
}