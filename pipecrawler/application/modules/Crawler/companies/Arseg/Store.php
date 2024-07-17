<?php

/* 
 * Store Crawler für ARS eG (ID: 71715)
 */

class Crawler_Company_Arseg_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.arseg.de/';
        $searchUrl = $baseUrl . 'ars-fachhaendler/ars-fachhaendler';
    }
}