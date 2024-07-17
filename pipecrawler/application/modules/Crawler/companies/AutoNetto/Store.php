<?php

/*
 * Store Crawler für Auto Netto (ID: 71782)
 */

class Crawler_Company_AutoNetto_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.autonetto.de/';
        $searchUrl = $baseUrl . 'Home/WerkstattSuche/tabid/851/location/'
//                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '94469'
                . '/distance/1000/Default.aspx';
        $detailUrl = $baseUrl . 'Home/WerkstattSuche/WerkstattSuche/tabid/852/wid/'
                . $storeId . '/Default.aspx';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $oPage = $sPage->getPage();
        $oPage->setUseCookies(TRUE);
        $sPage->setPage($oPage);

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 25);

        $cStores = new Marktjagd_Collection_Api_Store();
        $aStoreIds = array();
        
        foreach ($aUrls as $singleUrl)
        {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            Zend_Debug::dump($page);die;
            $pattern = '#http\:\/\/www\.autonetto\.de\/Footer\/Kontakt\/tabid\/854\/wid\/([^\/]+?)\/Default.aspx#s';
            if (!preg_match_all($pattern, $page, $storeIdMatches))
            {
                $this->_logger->info($companyId . ': unable to get any stores: ' . $singleUrl);
                continue;
            }
            foreach ($storeIdMatches[1] as $singleId) {
                    $aStoreIds[] = $singleId;
            }
        }
        
        Zend_Debug::dump($aStoreIds);
        array_unique($aStoreIds);
        Zend_Debug::dump($aStoreIds);
        die;
    }

}
