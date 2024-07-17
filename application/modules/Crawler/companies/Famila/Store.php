<?php

/**
 * Store Crawler für Famila Nordost (ID: 28975)
 */
class Crawler_Company_Famila_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.famila-nordost.de/';
        $searchUrl = $baseUrl . 'famila-warenhausuebersicht/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        
        if (!$sPage->open($searchUrl)) {
            throw new Exception ($companyId . ': unable to open store list page.');
        }
        
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '##';
    }
}