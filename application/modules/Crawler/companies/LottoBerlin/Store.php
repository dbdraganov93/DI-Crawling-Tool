<?php

/*
 * Store Crawler für Lotto Berlin (ID: )
 */

class Crawler_Company_LottoBerlin_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.lotto-berlin.de/';
        $searchUrl = $baseUrl . 'pfe/controller/RetailerController/showShopsWithinRadius?'
                . 'gbn=7&loc=de&jdn=7&lat=52.4922134&lon=13.303749799999991&zip='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 50);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($baseUrl . 'pfe/controller/RetailerController/showShopsWithinRadius?'
                . 'gbn=7&loc=de&jdn=7&lat=52.4922134&lon=13.303749799999991&zip=18551');
            $page = $sPage->getPage()->getResponseBody();
            
            Zend_Debug::dump($page);die;
            
            $pattern = '#var\s*box_html\s*=\"(.+?)\);#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': no stores for: ' . $singleUrl);
                continue;
            }
            Zend_Debug::dump($storeMatches[1]);
            die;
        }
    }

}
