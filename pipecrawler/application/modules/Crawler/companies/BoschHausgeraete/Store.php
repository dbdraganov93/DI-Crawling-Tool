<?php

/*
 * Store Crawler für Robert Bosch Hausgeräte (ID: 71872)
 */

class Crawler_Company_BoschHausgeraete_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.bosch-home.com/';
        $searchUrl = $baseUrl . urlencode('de/händler-finden.html');
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();
        
        $oPage = $sPage->getPage();
        $oPage->setUseCookies(TRUE);
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);
        
        $aZipcodes = $sDbGeo->findAllZipCodes();
        
        $aParams = array(
            '72' => '1526,1530',
            'types' => '1,1,1,1,3,2,6,1'
        );
        
        foreach ($aZipcodes as $singleZipcode) {
            $aParams['83'] = $singleZipcode;
            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();     
            
            $pattern = '#var\s*dealerObj\s*=\s*([^;]+?);#is';
            if (!preg_match_all($pattern, $page, $storeDetailMatches)) {
                continue;
            }

            foreach ($storeDetailMatches[1] as $singleStore) {
                Zend_Debug::dump(json_decode($singleStore));
                die;
            }
        }
    }

}
