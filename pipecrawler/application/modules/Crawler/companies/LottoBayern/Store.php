<?php

/*
 * Store Crawler für Lotto Bayern (ID: 71774)
 */

class Crawler_Company_LottoBayern_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.lotto-bayern.de/';
        $searchUrl = $baseUrl . 'pfe/controller/RetailerController/showShopList';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);
        $sAddress = new Marktjagd_Service_Text_Address();

        $oPage = $sPage->getPage();
//        $oPage->setMethod('POST');
        $oPage->setUseCookies(TRUE);
        $sPage->setPage($oPage);
//
//        $aParams = array(
//            'gbn' => '2',
//            'loc' => 'de',
//            'jdn' => '2'
//        );

        $cStores = new Marktjagd_Collection_Api_Store();
        
        $sPage->open('');
        $page = $sPage->getPage()->getResponseBody();
        Zend_Debug::dump($page);
        die;
        $pattern = '#var\s*box_html\s*=\"(.+?)\);#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            $this->_logger->info($companyId . ': no stores for: ' . $singleUrl);
            die;
        }
        Zend_Debug::dump($storeMatches[1]);
        die;
    }

}
