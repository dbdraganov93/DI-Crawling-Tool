<?php

/**
 * Store Crawler für Cap Markt (ID: 28974)
 */
class Crawler_Company_Cap_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.cap-markt.de/';
        $searchUrl = $baseUrl . 'cap-maerkte/cap-marktsuche.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*mylist=\[(.+?)\]#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            
        }
        Zend_Debug::dump($storeListMatch[1]);die;
    }
}