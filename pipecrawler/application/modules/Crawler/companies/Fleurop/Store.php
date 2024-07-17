<?php

/**
 * Store Crawler für Fleurop (ID: 410)
 */
class Crawler_Company_Fleurop_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.fleurop.de/';
        $searchUrl = $baseUrl . 'shop/florists.aspx';
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sDb = new Marktjagd_Database_Service_GeoRegion();

        $aZipcodes = $sDb->findAllZipCodes();

        foreach ($aZipcodes as $zip) {
            $sPage = new Marktjagd_Service_Input_Page();
            $oPage = $sPage->getPage();
            $oPage->setMethod('POST');
            $sPage->setPage($oPage);

            $aParams = array(
                '__EVENTTARGET' => htmlentities('ctl00$cphContent$searchFlorist'),
                htmlentities('ctl00$cphContent$txtZipcode') => $zip
            );

            if (!$sPage->open($searchUrl, $aParams)) {
                throw new Exception($companyId . ': unable to open store list page for zip: ' . $zip);
            }
            
            $page = $sPage->getPage()->getResponseBody();
            
            Zend_Debug::dump($page);die;
        }
    }

}
