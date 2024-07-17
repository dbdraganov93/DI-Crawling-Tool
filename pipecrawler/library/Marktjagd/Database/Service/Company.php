<?php

class Marktjagd_Database_Service_Company extends Marktjagd_Database_Service_Abstract
{
    /**
     * Ermittelt alle verfügbaren Unternehmen
     * @return Marktjagd_Database_Collection_Company
     */
    public function findAll()
    {
        $cCrawlerCompany = new Marktjagd_Database_Collection_Company();
        $mCrawlerCompany = new Marktjagd_Database_Mapper_Company();
        $mCrawlerCompany->fetchAll(null, $cCrawlerCompany);
        return $cCrawlerCompany;
    }
    
    /**
     * 
     * @param string $companyId
     * @return Marktjagd_Database_Entity_Company
     */
    public function find($companyId)
    {
        $eCrawlerCompany = new Marktjagd_Database_Entity_Company();
        $mCrawlerCompany = new Marktjagd_Database_Mapper_Company();
        $mCrawlerCompany->find($companyId, $eCrawlerCompany);
        
        return $eCrawlerCompany;
    }
}