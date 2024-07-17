<?php

class Crawler_Company_Kaufland_ArticleNextWeek extends Crawler_Generic_Company {
 
    public function crawl($companyId) {        
        $crawlClass = new Crawler_Company_Kaufland_Article();
        $crawlClass->setStoreUrl(array('http://kaufland.de/Home/01_Angebote/Vorwerbung/index.jsp'));
        return $crawlClass->crawl($companyId);        
    }    
}


