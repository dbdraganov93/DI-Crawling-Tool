<?php

class Marktjagd_Database_Service_Author extends Marktjagd_Database_Service_Abstract
{
    /**
     * Ermittelt alle verfügbaren Crawler-Autoren
     * @return Marktjagd_Database_Collection_Author
     */
    public function findAll()
    {
        $cCrawlerAuthor = new Marktjagd_Database_Collection_Author();
        $mCrawlerAuthor = new Marktjagd_Database_Mapper_Author();
        $mCrawlerAuthor->fetchAll(null, $cCrawlerAuthor);
        return $cCrawlerAuthor;
    }
}